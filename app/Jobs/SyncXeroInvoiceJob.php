<?php

namespace App\Jobs;

use App\ConfigRefreshXero;
use App\Models\InvoicesAllFromXero;
use App\Models\ItemsPaketAllFromXero;
use App\Models\MasterData\BankXero;
use App\Models\MasterData\Coa;
use App\Models\MasterData\DataJamaahXero;
use App\Models\MasterData\ItemDetailInvoices;
use App\Models\SyncJobStatus;
use App\Models\Transaction\TransactionAllCoa;
use App\Models\Transaction\TransactionNominalBankAccount;
use App\Services\GlobalService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncXeroInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, ConfigRefreshXero;



    public int $tries = 3;
    public int $timeout = 600;

    private array $tokenData;
    private string $jobId;

    // Xero hanya kembalikan 100 invoice per halaman
    private const PER_PAGE = 100;
    private const MIN_REM_THRESHOLD = 5;
    protected $service_global;

    public function __construct(array $tokenData, string $jobId)
    {

        $this->tokenData = $tokenData;
        $this->jobId = $jobId;
        $this->service_global = new GlobalService();
    }

    public function handle(): void
    {
        // SyncJobStatus::where('job_id', $this->jobId)->update([
        //     'status' => 'running',
        //     'started_at' => now(),
        // ]);

        try {
            $accessToken = $this->tokenData['access_token'];
            $tenantId = $this->getTenantId($accessToken);
            $page = 1;
            $totalSynced = 0;

            Log::info("[SyncXeroInvoiceJob][$this->jobId] Mulai sync invoice...");

            do {
                // SyncJobStatus::where('job_id', $this->jobId)->update([
                //     'current_page' => $page,
                // ]);

                $response = $this->fetchPage($accessToken, $tenantId, $page);

                if (!$response || !$response->successful()) {
                    throw new \Exception("Gagal fetch halaman $page. Status: " . (optional($response)->status() ?? 'null'));
                }

                // --- Rate limit guard ---
                $minRem = (int) $response->header('X-MinLimit-Remaining');
                $dayRem = (int) $response->header('X-DayLimit-Remaining');


                $this->service_global->requestCalculationXero($minRem, $dayRem);

                Log::info("[SyncXeroInvoiceJob][$this->jobId] Page $page | MinRem: $minRem | DayRem: $dayRem");

                if ($minRem <= self::MIN_REM_THRESHOLD) {
                    throw new \Exception("Rate limit hampir habis ($minRem/menit). Berhenti di page $page.");
                }

                $invoices = $response->json('Invoices') ?? [];

                foreach ($invoices as $inv) {
                    $this->processInvoice($inv);
                    $totalSynced++;
                }

                SyncJobStatus::where('job_id', $this->jobId)->update([
                    'total_synced' => $totalSynced,
                    'total_pages' => $page,
                ]);

                Log::info("[SyncXeroInvoiceJob][$this->jobId] Page $page selesai. Total tersimpan: $totalSynced");

                $hasNextPage = count($invoices) === self::PER_PAGE;
                $page++;

                if ($hasNextPage) {
                    usleep(300_000); // 300ms throttle
                }

            } while ($hasNextPage);

            // SyncJobStatus::where('job_id', $this->jobId)->update([
            //     'status' => 'success',
            //     'total_synced' => $totalSynced,
            //     'finished_at' => now(),
            // ]);

            Log::info("[SyncXeroInvoiceJob][$this->jobId] Selesai. Total invoice: $totalSynced");

        } catch (\Exception $e) {
            // SyncJobStatus::where('job_id', $this->jobId)->update([
            //     'status' => 'failed',
            //     'error_message' => $e->getMessage(),
            //     'finished_at' => now(),
            // ]);

            Log::error("[SyncXeroInvoiceJob][$this->jobId] Error: " . $e->getMessage());
            throw $e;
        }
    }

    // ----------------------------------------------------------------
    // Proses 1 invoice: upsert parent → upsert detail line items
    // ------------
    // 
    // ----------------------------------------------------




    public function getDetailPayment(string $idPayment)
    {
        $accessToken = $this->tokenData['access_token'];
        $tenantId = $this->getTenantId($accessToken);

        $response_detail = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Xero-Tenant-Id' => $tenantId,
            'Accept' => 'application/json',
        ])->timeout(25)->get("https://api.xero.com/api.xro/2.0/Payments/$idPayment");

        if ($response_detail->failed()) {
            throw new \Exception("Gagal Get Detail Payment $idPayment: " . $response_detail->body());
        }

        // Guard rate limit juga di sini, bukan cuma di fetch invoice list
        $minRem = (int) $response_detail->header('X-MinLimit-Remaining');
        if ($minRem > 0 && $minRem <= self::MIN_REM_THRESHOLD) {
            throw new \Exception("Rate limit hampir habis saat fetch payment $idPayment ($minRem/menit).");
        }

        $data = $response_detail->json();
        $payment = $data['Payments'][0] ?? null;


        $dayRem = (int) $response_detail->header('X-DayLimit-Remaining');
        $this->service_global->requestCalculationXero($minRem, $dayRem);

        dd($data);
        if (!$payment) {
            Log::warning("[getDetailPayment] Payment $idPayment tidak ditemukan/kosong di response Xero, dilewati.");
            return;
        }

        $amount = $payment['Amount'] ?? 0;
        $account_code = data_get($payment, 'Account.Code');
        $date = $this->parseXeroDate($payment['Date'] ?? null);
        $invoiceUuid = data_get($payment, 'Invoice.InvoiceID');
        $bankName = data_get($payment, 'Account.Name');
        $invoiceNumber = data_get($payment, 'Invoice.InvoiceNumber');

        $id_parent_inv = $invoiceUuid
            ? InvoicesAllFromXero::where('invoice_uuid', $invoiceUuid)->value('id')
            : null;

        $this->insertToDb($invoiceNumber, $bankName, $idPayment, $amount, $account_code, $date, $invoiceUuid, $id_parent_inv);

        usleep(150_000); // throttle kecil, payment fetch ikut makan quota rate limit Xero
    }


    public function insertToDb($invNumber, $namaBank, $paymentUuid, $amount, $account_code, $date, $ref_detail, $id_parent_inv)
    {
        $findBank = BankXero::where('code', $account_code)->first();

        if (!$findBank) {
            Log::warning("[insertToDb] Kode akun bank tidak ditemukan: '{$account_code}'. Payment {$paymentUuid} dilewati. nama bank {$namaBank} INVOICE : $invNumber");
            return;
        }

        // updateOrCreate, bukan create() polos → mencegah duplikat saat job di-retry/cron ulang
        TransactionNominalBankAccount::updateOrCreate(
            ['payment_uuid' => $paymentUuid],
            [
                'uuid_bank' => $findBank->id,
                'nominal_receive' => $amount,
                'created_by' => 1,
                'date_transaction' => $date,
                'nominal_spend' => 0,
                'nominal_transfer' => 0,
                'reference_detail' => $ref_detail,
                'id_parent_invoice' => $id_parent_inv,
            ]
        );
    }

    private function processInvoice(array $inv): void
    {
        $lineItems = $inv['LineItems'] ?? [];
        $firstLine = $lineItems[0] ?? [];
        $glbl = new GlobalService();
        $issueDate = $this->parseXeroDate($inv['DateString'] ?? $inv['Date'] ?? null);
        $dueDate = $this->parseXeroDate($inv['DueDateString'] ?? $inv['DueDate'] ?? null);

        $findContact = DataJamaahXero::where('uuid_contact', data_get($inv, 'Contact.ContactID'))->pluck('id')->first() ?? 1;


        if (count($inv['Payments']) > 0) {
            foreach ($inv['Payments'] as $key2 => $value2) {
                self::getDetailPayment($value2['PaymentID']);
            }
        }
        // --- Upsert parent invoice ---
        $invoiceData = [
            'invoice_uuid' => $inv['InvoiceID'],
            'invoice_number' => $inv['InvoiceNumber'] ?? null,
            'invoice_amount' => $inv['AmountPaid'] ?? 0,//$inv['AmountDue'] ?? 0,
            'invoice_total' => $inv['Total'] ?? 0,
            'less_nominal' => $inv['AmountDue'] ?? 0, //$inv['TotalDiscount'] ?? 0,
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'status' => $inv['Status'] ?? null,
            'uuid_contact' => data_get($inv, 'Contact.ContactID'),
            'contact_name' => data_get($inv, 'Contact.Name'),
            'contact_id' => $findContact,//data_get($inv, 'Contact.ContactID'),
            'uuid_proudct_and_service' => $firstLine['ItemID'] ?? null,
            'item_name' => isset($firstLine['Description']) ? $firstLine['Description'] : null,
            'reference' => $inv['Reference'] ?? null,
            'updated_at' => now(),
            'created_at' => now(),
        ];

        InvoicesAllFromXero::upsert(
            [$invoiceData],
            ['invoice_uuid'],
            [
                'invoice_number',
                'invoice_amount',
                'invoice_total',
                'less_nominal',
                'issue_date',
                'due_date',
                'status',
                'uuid_contact',
                'contact_name',
                'contact_id',
                'uuid_proudct_and_service',
                'item_name',
                'reference',
                'updated_at',
            ]
        );

        $parentId = InvoicesAllFromXero::where('invoice_uuid', $inv['InvoiceID'])->value('id');

        if (!$parentId || empty($lineItems))
            return;

        // ----------------------------------------------------------------
        // Pre-load COA dan Item SEKALI sebelum loop — hindari N+1 query
        // ----------------------------------------------------------------

        // Kumpulkan semua AccountCode dan Item Code dari semua line items dulu
        $accountCodes = collect($lineItems)
            ->pluck('AccountCode')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $itemCodes = collect($lineItems)
            ->filter(fn($l) => isset($l['Item']['Code']))
            ->map(fn($l) => $l['Item']['Code'])
            ->unique()
            ->values()
            ->toArray();

        // Query sekali untuk semua COA yang dibutuhkan → simpan ke array [code => id]
        $coaMap = Coa::whereIn('code', $accountCodes)
            ->pluck('id', 'code')   // ['1001' => 5, '2001' => 12, ...]
            ->toArray();

        // Query sekali untuk semua Item yang dibutuhkan → simpan ke array [code => id]
        $itemMap = ItemsPaketAllFromXero::whereIn('code', $itemCodes)
            ->pluck('id', 'code')   // ['PKT-001' => 3, 'PKT-002' => 7, ...]
            ->toArray();

        // ----------------------------------------------------------------
        // Loop line items — semua lookup dari array, TIDAK ada query di sini
        // ----------------------------------------------------------------
        $batchDetails = [];

        foreach ($lineItems as $line) {
            // Ambil tracking per line item
            $paketUuid = null;
            $divisiUuid = null;


            if (isset($line['Tracking'])) {
                foreach ($line['Tracking'] ?? [] as $track) {


                    $categoryName = strtolower($track['Name'] ?? '');
                    //$optionUuid = $track['TrackingCategoryID'] ?? null;



                    if (str_contains($categoryName, 'nama paket')) {
                        $kategori_paket = DB::table('tracking_categories')
                            ->where('name_parent_category', 'Nama Paket')
                            ->whereJsonContains('lines_category', ['item_name_category' => $track['Option']])
                            ->first();

                        if ($kategori_paket) {
                            $lines = collect(json_decode($kategori_paket->lines_category, true));
                            $itemSpesifik = $lines->firstWhere('item_name_category', $track['Option']);
                            $uuidPaket = $itemSpesifik['item_uuid_category'];
                            $paketUuid = $uuidPaket;
                        }
                    } elseif (str_contains($categoryName, 'divisi')) {

                        $kategori_divisi = DB::table('tracking_categories')
                            ->where('name_parent_category', 'Divisi')
                            ->whereJsonContains('lines_category', ['item_name_category' => $track['Option']])
                            ->first();

                        if ($kategori_divisi) {
                            $lines = collect(json_decode($kategori_divisi->lines_category, true));
                            $itemSpesifik = $lines->firstWhere('item_name_category', $track['Option']);
                            $uuid_divisi = $itemSpesifik['item_uuid_category'];
                            $divisiUuid = $uuid_divisi;
                        }


                    }

                }
            }



            // Lookup COA dari map — default 11 kalau tidak ketemu
            $coaId = isset($line['AccountCode'])
                ? ($coaMap[$line['AccountCode']] ?? null)
                : null;

            // Lookup Item dari map — ambil dari line item yang sedang diproses
            $itemCode = $line['Item']['Code'] ?? null;
            $itemIdSave = $itemCode ? ($itemMap[$itemCode] ?? null) : null;
            $uuidItem = $line['Item']['ItemID'] ?? $line['ItemID'] ?? 'no_set';


            $batchDetails[] = [
                'invoice_number' => $inv['InvoiceNumber'] ?? null,
                'uuid_invoices' => $inv['InvoiceID'],
                'uuid_item' => $uuidItem,
                'qty' => $line['Quantity'] ?? 0,
                'unit_price' => $line['UnitAmount'] ?? 0,
                'total_amount_each_row' => $line['LineAmount'] ?? 0,
                'line_item_uuid' => $line['LineItemID'],
                'coa_id' => $coaId,
                'parent_inv_id' => $parentId,
                'item_id' => $itemIdSave,
                'uuid_detail_inv' => $glbl->generateUniqueString(),
                'paket_tracking_uuid' => $paketUuid,
                'divisi_travel_tracking_uuid' => $divisiUuid,
                'desc' => $line['Description'] ?? null,
                'updated_at' => now(),
                'created_at' => now(),
            ];


        }

        if (!empty($batchDetails)) {
            ItemDetailInvoices::upsert(
                $batchDetails,
                ['line_item_uuid'],
                [
                    'invoice_number',
                    'uuid_invoices',
                    'uuid_item',
                    'qty',
                    'unit_price',
                    'total_amount_each_row',
                    'coa_id',
                    'parent_inv_id',
                    'item_id',
                    'paket_tracking_uuid',
                    'divisi_travel_tracking_uuid',
                    'desc',
                    'updated_at',
                    'uuid_detail_inv'
                ]
            );
        }

        $savedDetails = ItemDetailInvoices::whereIn(
            'line_item_uuid',
            collect($batchDetails)->pluck('line_item_uuid')->toArray()
        )->get()->keyBy('line_item_uuid');

        if ($inv['Status'] == 'AUTHORISED' || $inv['Status'] == 'PAID') {
            foreach ($batchDetails as $detail) {
                if (empty($detail['coa_id']))
                    continue;

                $saved = $savedDetails[$detail['line_item_uuid']] ?? null;
                if (!$saved)
                    continue;

                TransactionAllCoa::firstOrCreate(

                    ['uuid_detail' => $saved->uuid_detail_inv],
                    [
                        'date_transaction' => $issueDate,
                        'uuid_coa' => $detail['coa_id'],
                        'reference' => $inv['Reference'] ?? '-',
                        'is_speend' => 0,
                        'nominal' => abs((int) $saved->total_amount_each_row),//selalu positif
                        'uuid_detail' => $saved->uuid_detail_inv,
                    ]
                );
            }
        }

    }

    // ----------------------------------------------------------------
    // Fetch 1 halaman invoice dari Xero
    // Type=ACCREC → hanya Sales Invoice (bukan Bill)
    // ----------------------------------------------------------------
    private function fetchPage(string $accessToken, string $tenantId, int $page)
    {
        try {
            return Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Xero-Tenant-Id' => $tenantId,
                'Accept' => 'application/json',
            ])
                ->timeout(25)
                ->get('https://api.xero.com/api.xro/2.0/Invoices', [
                    'Statuses' => 'DRAFT,SUBMITTED,AUTHORISED,PAID',
                    'Type' => 'ACCREC',
                    'order' => 'Date DESC',
                    'page' => $page,
                    'unitdp' => 4,
                ]);

            // Tambahkan ini untuk debug
            if (!$response->successful()) {
                Log::error("[SyncXeroInvoiceJob] 403 body: " . $response->body());
            }

            return $response;
        } catch (\Exception $e) {
            Log::error("[SyncXeroInvoiceJob] Exception fetch page $page: " . $e->getMessage());
            return null;
        }
    }

    // ----------------------------------------------------------------
    // Parse format tanggal Xero: bisa string ISO atau /Date(epoch)/
    // ----------------------------------------------------------------
    private function parseXeroDate(?string $dateStr): ?string
    {
        if (!$dateStr)
            return null;

        // Format ISO: "2024-01-15T00:00:00"
        if (str_contains($dateStr, 'T') || str_contains($dateStr, '-')) {
            try {
                return Carbon::parse($dateStr)->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }

        // Format epoch: "/Date(1704067200000+0000)/"
        if (preg_match('/\/Date\((\d+)/', $dateStr, $matches)) {
            return Carbon::createFromTimestampMs((int) $matches[1])->format('Y-m-d');
        }

        return null;
    }

}