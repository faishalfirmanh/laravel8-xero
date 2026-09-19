<?php

namespace App\Jobs;

use App\ConfigRefreshXero;
use App\Models\Expenses\Purchase\Bill\PBill;
use App\Models\Expenses\Purchase\Bill\DBill;
use App\Models\MasterData\BankXero;
use App\Models\MasterData\Coa;
use App\Models\MasterData\DataJamaahXero;
use App\Models\SyncJobStatus;
use App\Models\Transaction\TransactionAllCoa;
use App\Models\Transaction\TransactionNominalBankAccount;
use App\Services\GlobalService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncBillJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, ConfigRefreshXero;

    public int $timeout = 700;

    private const PER_PAGE = 100;
    private const MIN_REM_THRESHOLD = 5;
    private const SLOWDOWN_THRESHOLD = 15;
    private const THROTTLE_PAGE_US = 400_000;
    private const THROTTLE_PAYMENT_US = 200_000;
    private const THROTTLE_SLOW_US = 1_000_000;

    private array $tokenData;
    private string $jobId;
    private bool $shouldRelease = false;
    private int $releaseAfterSecs = 60;
    private array $trackingCache = [];
    private ?string $tenantId = null;

    protected $service_global;

    public function __construct(array $tokenData, string $jobId)
    {
        $this->tokenData = $tokenData;
        $this->jobId = $jobId;
        $this->service_global = new GlobalService();
    }

    public function retryUntil(): \DateTime
    {
        return now()->addHours(26);
    }

    // ================================================================
    // MAIN ENTRY POINT
    // ================================================================

    public function handle(): void
    {
        try {
            SyncJobStatus::where('job_id', $this->jobId)->update([
                'status' => 'running',
                'started_at' => now(),
            ]);

            $accessToken = $this->tokenData['access_token'];
            $this->tenantId = $this->getTenantId($accessToken);
            $tenantId = $this->tenantId;

            $page = 1;
            $totalSynced = 0;
            Log::info("[SyncBillJob][$this->jobId] Mulai sync bill (ACCPAY)...");

            do {
                $response = $this->fetchPage($accessToken, $tenantId, $page);

                if ($response === null) {
                    throw new \RuntimeException("fetchPage() mengembalikan null pada page $page (exception jaringan).");
                }

                if ($response->status() === 429) {
                    $retryAfter = (int) ($response->header('Retry-After') ?? 60);
                    Log::warning("[SyncBillJob][$this->jobId] Rate limited (429) di page $page. Re-queue {$retryAfter}s.");
                    $this->triggerRelease($retryAfter);
                    break;
                }

                if (!$response->successful()) {
                    throw new \RuntimeException(
                        "Gagal fetch halaman $page. HTTP {$response->status()}: " . substr($response->body(), 0, 300)
                    );
                }

                $this->guardRateLimit($response, "bill-list page $page");
                if ($this->shouldRelease) {
                    break;
                }

                $bills = $response->json('Invoices') ?? [];

                foreach ($bills as $bill) {
                    if ($this->shouldRelease) {
                        break;
                    }

                    try {
                        $this->processBills($bill);
                        $totalSynced++;
                    } catch (\Exception $e) {
                        // ❌ JANGAN throw exception — catat & lanjut ke bill berikutnya
                        // Ini mencegah 1 bill yang error menyebabkan seluruh halaman gagal
                        Log::error(
                            "[SyncBillJob][$this->jobId] Error process bill {$bill['InvoiceNumber']}: " .
                            $e->getMessage()
                        );
                        // Lanjut ke bill berikutnya
                        continue;
                    }
                }

                SyncJobStatus::where('job_id', $this->jobId)->update([
                    'total_synced' => $totalSynced,
                    'total_pages' => $page,
                ]);

                Log::info("[SyncBillJob][$this->jobId] Page $page selesai. Total tersimpan: $totalSynced");

                if ($this->shouldRelease) {
                    break;
                }

                $hasNextPage = count($bills) === self::PER_PAGE;
                $page++;

                if ($hasNextPage) {
                    usleep(self::THROTTLE_PAGE_US);
                }

            } while ($hasNextPage);

            if ($this->shouldRelease) {
                Log::warning(
                    "[SyncBillJob][$this->jobId] Kuota Xero kritis. " .
                    "Job di-release, lanjut otomatis setelah {$this->releaseAfterSecs}s. " .
                    "Progress tersimpan: $totalSynced bill."
                );
                $this->release($this->releaseAfterSecs);
                return;
            }

            SyncJobStatus::where('job_id', $this->jobId)->update([
                'status' => 'success',
                'finished_at' => now(),
            ]);

            Log::info("[SyncBillJob][$this->jobId] Selesai. Total bill: $totalSynced");

        } catch (\Exception $e) {
            SyncJobStatus::where('job_id', $this->jobId)->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            Log::error("[SyncBillJob][$this->jobId] Error: " . $e->getMessage());
            throw $e;
        }
    }

    // ================================================================
    // RATE LIMIT GUARD
    // ================================================================

    private function guardRateLimit(Response $response, string $context): void
    {
        $minRemHeader = $response->header('X-MinLimit-Remaining');
        $dayRemHeader = $response->header('X-DayLimit-Remaining');

        if ($minRemHeader === null || $minRemHeader === '') {
            return;
        }

        $minRem = (int) $minRemHeader;
        $dayRem = (int) ($dayRemHeader ?? 0);

        $this->service_global->requestCalculationXero($minRem, $dayRem);

        Log::info("[SyncBillJob][$this->jobId] [$context] MinRem: $minRem | DayRem: $dayRem");

        if ($minRem <= self::MIN_REM_THRESHOLD) {
            Log::warning("[SyncBillJob][$this->jobId] Kuota kritis ($minRem/menit) di $context.");
            $this->triggerRelease(65);
            return;
        }

        if ($minRem <= self::SLOWDOWN_THRESHOLD) {
            usleep(self::THROTTLE_SLOW_US);
        }
    }

    private function triggerRelease(int $seconds): void
    {
        $this->shouldRelease = true;
        $this->releaseAfterSecs = max($this->releaseAfterSecs, $seconds);
    }

    // ================================================================
    // STATUS MAPPER
    // ================================================================

    private function mapBillStatus(?string $xeroStatus): int
    {
        switch ($xeroStatus) {
            case 'PAID':
                return 2;
            case 'SUBMITTED':
            case 'AUTHORISED':
                return 1;
            case 'DRAFT':
            case 'VOIDED':
            case 'DELETED':
            default:
                return 0;
        }
    }

    // ================================================================
    // CURRENCY & CONVERSION
    // ================================================================

    private function getXeroCurrencyRate(array $data, string $currency): float
    {
        $currency = strtoupper(trim($currency));
        if ($currency === 'IDR') {
            return 1.0;
        }

        $rate = isset($data['CurrencyRate'])
            ? (float) $data['CurrencyRate']
            : 0;

        if ($rate <= 0) {
            throw new \InvalidArgumentException(
                "CurrencyRate Xero tidak ditemukan/invalid untuk currency {$currency}."
            );
        }

        return $rate;
    }

    private function convertToBase(float $amount, float $currencyRate): int
    {
        if ($amount <= 0) {
            return 0;
        }

        if ($currencyRate <= 0) {
            throw new \InvalidArgumentException("Currency rate tidak valid: {$currencyRate}");
        }

        return (int) round($amount / $currencyRate, 0);
    }

    private function mapAmountsAre(?string $xeroLineAmountTypes): int
    {
        switch ($xeroLineAmountTypes) {
            case 'Inclusive':
                return 1;
            case 'NoTax':
                return 0;
            case 'Exclusive':
            default:
                return 2;
        }
    }

    // ================================================================
    // PAYMENT SYNC (dengan dedup restored!)
    // ================================================================

    public function getDetailPayment(string $idPayment, ?int $knownParentId = null): void
    {
        if ($this->shouldRelease) {
            return;
        }

        // ✅ CEK DUPLIKAT DULU — kalau sudah ada, skip
        $alreadySynced = TransactionNominalBankAccount::where('payment_uuid', $idPayment)->exists();
        if ($alreadySynced) {
            Log::info("[SyncBillJob][getDetailPayment] Payment $idPayment sudah tersimpan, skip duplikat.");
            return;
        }

        $accessToken = $this->tokenData['access_token'];
        $tenantId = $this->getTenantId($accessToken);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Xero-Tenant-Id' => $tenantId,
            'Accept' => 'application/json',
        ])->timeout(25)->get("https://api.xero.com/api.xro/2.0/Payments/$idPayment");

        if ($response->status() === 429) {
            $retryAfter = (int) ($response->header('Retry-After') ?? 60);
            Log::warning("[SyncBillJob][getDetailPayment] Rate limited (429) payment $idPayment. Release {$retryAfter}s.");
            $this->triggerRelease($retryAfter);
            return;
        }

        if ($response->failed()) {
            throw new \RuntimeException("Gagal Get Detail Payment $idPayment: " . $response->body());
        }

        $this->guardRateLimit($response, "payment $idPayment");
        if ($this->shouldRelease) {
            return;
        }

        $payment = $response->json('Payments.0');

        if (!$payment) {
            Log::warning("[SyncBillJob][getDetailPayment] Payment $idPayment tidak ditemukan/kosong di response Xero, dilewati.");
            return;
        }

        $paymentCurrency = strtoupper(
            $payment['CurrencyCode']
            ?? data_get($payment, 'Account.CurrencyCode')
            ?? 'IDR'
        );

        // ✅ GUARD: validation currency rate SEBELUM proses lebih jauh
        try {
            $paymentCurrencyRate = $this->getXeroCurrencyRate($payment, $paymentCurrency);
        } catch (\InvalidArgumentException $e) {
            Log::warning(
                "[SyncBillJob][getDetailPayment] " . $e->getMessage() .
                " Payment: {$idPayment}, Currency: {$paymentCurrency} — SKIP payment ini."
            );
            return; // Skip payment dengan rate invalid, tapi jangan crash job
        }

        $amount = (float) ($payment['Amount'] ?? 0);
        $accountCode = data_get($payment, 'Account.Code');
        $bankName = data_get($payment, 'Account.Name');
        $date = $this->parseXeroDate($payment['Date'] ?? null);
        $invoiceUuid = data_get($payment, 'Invoice.InvoiceID');
        $invoiceNumber = data_get($payment, 'Invoice.InvoiceNumber');
        $ref_payment = $payment['Reference'] ?? '-';

        $idParentInv = $knownParentId
            ?? ($invoiceUuid ? PBill::where('bills_uuid_xero', $invoiceUuid)->value('id') : null);

        Log::info(
            "[SyncBillJob][getDetailPayment] " .
            "invoice: {$invoiceNumber} | bank: {$bankName} | payment_id: {$idPayment} | " .
            "amount: {$amount} | parent_id: {$idParentInv} | currency: {$paymentCurrency}"
        );

        $this->insertToDb(
            $invoiceNumber,
            $bankName,
            $idPayment,
            $amount,
            $accountCode,
            $date,
            $ref_payment,
            $idParentInv,
            $paymentCurrency,
            $paymentCurrencyRate
        );

        usleep(self::THROTTLE_PAYMENT_US);
    }

    public function insertToDb(
        ?string $invNumber,
        ?string $namaBank,
        string $paymentUuid,
        float $amount,
        ?string $accountCode,
        ?string $date,
        ?string $refDetail,
        ?int $idParentInv,
        ?string $paymentCurrency = null,
        float $paymentCurrencyRate = 1.0
    ): void {
        if (!$accountCode) {
            Log::warning(
                "[SyncBillJob][insertToDb] AccountCode kosong. " .
                "Payment {$paymentUuid} dilewati. Bill: {$invNumber}"
            );
            return;
        }

        $findBank = BankXero::where('code', $accountCode)->first();

        if (!$findBank) {
            Log::warning(
                "[SyncBillJob][insertToDb] Kode akun bank tidak ditemukan: '{$accountCode}'. " .
                "Payment {$paymentUuid} dilewati. Nama bank: {$namaBank}. Bill: {$invNumber}"
            );
            return;
        }

        $bankCurrency = strtoupper(
            trim((string) ($findBank->currency_code ?: $paymentCurrency ?: 'IDR'))
        );

        $totalBaseSpend = $this->convertToBase(
            $amount,
            $paymentCurrencyRate
        );

        TransactionNominalBankAccount::updateOrCreate(
            ['payment_uuid' => $paymentUuid],
            [
                'uuid_bank' => $findBank->id,
                'nominal_receive' => 0,
                'nominal_spend' => $amount,
                'nominal_currency' => $paymentCurrency == 'SAR' ? number_format(1 / $paymentCurrencyRate, 2, '.', '') : 1,
                'total_base_receive' => 0,
                'total_base_spend' => $totalBaseSpend,
                'created_by' => 1,
                'date_transaction' => $date,
                'nominal_transfer' => 0,
                'reference_detail' => $refDetail,
                'id_parent_bill' => $idParentInv,
            ]
        );
    }

    // ================================================================
    // BILL PROCESSING (dengan transaction!)
    // ================================================================

    private function processBills(array $inv): void
    {
        $xeroUuid = $inv['InvoiceID'] ?? null;

        if (!$xeroUuid) {
            Log::warning("[SyncBillJob][processBills] InvoiceID kosong, dilewati.");
            return;
        }

        try {
            $currencyCode = strtoupper($inv['CurrencyCode'] ?? 'IDR');
            $currencyRate = $this->getXeroCurrencyRate($inv, $currencyCode);
        } catch (\InvalidArgumentException $e) {
            Log::warning(
                "[SyncBillJob][processBills] " . $e->getMessage() .
                " Bill: {$inv['InvoiceNumber']} — SKIP bill ini."
            );
            return; // Skip bill dengan rate invalid
        }

        // ✅ GUNAKAN TRANSACTION untuk menjaga konsistensi data
        DB::transaction(function () use ($inv, $xeroUuid, $currencyCode, $currencyRate) {
            $lineItems = $inv['LineItems'] ?? [];
            $issueDate = $this->parseXeroDate($inv['DateString'] ?? $inv['Date'] ?? null);
            $dueDate = $this->parseXeroDate($inv['DueDateString'] ?? $inv['DueDate'] ?? null);
            $contactId = data_get($inv, 'Contact.ContactID');

            $findContact = DataJamaahXero::where('uuid_contact', $contactId)->value('id') ?? 1;

            // ────────────────────────────────────────────────────────────
            // ✅ 1. UPSERT parent bill dengan WHERE clause yang lebih aman
            // ────────────────────────────────────────────────────────────
            $parentId = PBill::updateOrCreate(
                ['bills_uuid_xero' => $xeroUuid],
                [
                    'uuid_from' => $findContact,
                    'date_req' => $issueDate,
                    'due_date' => $dueDate,
                    'reference' => $inv['InvoiceNumber'] ?? null,
                    'amounts_are' => $this->mapAmountsAre($inv['LineAmountTypes'] ?? null),
                    'subtotal' => $inv['SubTotal'] ?? 0,
                    'total' => $inv['Total'] ?? 0,
                    'tax' => $inv['TotalTax'] ?? 0,
                    'nominal_paid' => $inv['AmountPaid'] ?? 0,
                    'nominal_due' => $inv['AmountDue'] ?? 0,
                    'status' => $this->mapBillStatus($inv['Status'] ?? null),
                    'currency' => $currencyCode,
                    'nominal_currency' => $currencyCode == 'SAR' ? number_format(1 / $currencyRate, 2, '.', '') : 1,
                    'subtotal_base' => $this->convertToBase((float) ($inv['SubTotal'] ?? 0), $currencyRate),
                    'total_base' => $this->convertToBase((float) ($inv['Total'] ?? 0), $currencyRate),
                    'tax_base' => (int) ceil(((float) ($inv['TotalTax'] ?? 0)) * $currencyRate),
                    'nominal_paid_base' => $this->convertToBase((float) ($inv['AmountPaid'] ?? 0), $currencyRate),
                    'nominal_due_base' => $this->convertToBase((float) ($inv['AmountDue'] ?? 0), $currencyRate),
                    'created_by' => 1,
                    'updated_at' => now(),
                ]
            )->id;

            if (!$parentId) {
                throw new \RuntimeException("Gagal upsert parent bill: $xeroUuid");
            }

            // ────────────────────────────────────────────────────────────
            // ✅ 2. Sync payment (dedup sudah di getDetailPayment)
            // ────────────────────────────────────────────────────────────
            $payments = $inv['Payments'] ?? [];

            if (!empty($payments)) {
                foreach ($payments as $paymentRow) {
                    if ($this->shouldRelease) {
                        break;
                    }

                    $paymentId = $paymentRow['PaymentID'] ?? null;
                    if (!$paymentId) {
                        continue;
                    }

                    $this->getDetailPayment($paymentId, $parentId);
                }
            }

            if (empty($lineItems) || $this->shouldRelease) {
                return;
            }

            // ────────────────────────────────────────────────────────────
            // ✅ 3. Pre-load COA
            // ────────────────────────────────────────────────────────────
            $accountCodes = collect($lineItems)->pluck('AccountCode')->filter()->unique()->values()->toArray();
            $coaMap = Coa::whereIn('code', $accountCodes)->pluck('id', 'code')->toArray();

            // ────────────────────────────────────────────────────────────
            // ✅ 4. Build batch line items
            // ────────────────────────────────────────────────────────────
            $batchDetails = [];

            foreach ($lineItems as $line) {
                $paketUuid = null;
                $divisiUuid = null;

                foreach ($line['Tracking'] ?? [] as $track) {
                    $categoryName = strtolower($track['Name'] ?? '');
                    $optionName = $track['Option'] ?? '';

                    if (strpos($categoryName, 'nama paket') !== false) {
                        $paketUuid = $this->resolveTrackingUuid('Nama Paket', $optionName);
                    } elseif (strpos($categoryName, 'divisi') !== false) {
                        $divisiUuid = $this->resolveTrackingUuid('Divisi', $optionName);
                    }
                }

                $coaId = isset($line['AccountCode']) ? ($coaMap[$line['AccountCode']] ?? null) : null;
                $itemCode = $line['ItemCode'] ?? data_get($line, 'Item.Code');
                $uuidDetail = $line['LineItemID'] ?? $this->service_global->generateUniqueString();
                $lineAmount = (float) ($line['LineAmount'] ?? 0);

                $batchDetails[] = [
                    'bills_parent_id' => $parentId,
                    'item_code' => $itemCode,
                    'desc' => $line['Description'] ?? null,
                    'qty' => $line['Quantity'] ?? 0,
                    'unit_price' => $line['UnitAmount'] ?? 0,
                    'account_id_coa' => $coaId,
                    'tax_rate' => $line['TaxAmount'] ?? 0,
                    'paket_tracking_uuid' => $paketUuid,
                    'divisi_travel_tracking_uuid' => $divisiUuid,
                    'amount' => $lineAmount,
                    'total_base' => $this->convertToBase($lineAmount, $currencyRate),
                    'uuid_detail' => $uuidDetail,
                    'updated_at' => now(),
                    'created_at' => now(),
                ];
            }

            if (!empty($batchDetails)) {
                DBill::upsert(
                    $batchDetails,
                    ['uuid_detail'],
                    [
                        'bills_parent_id',
                        'item_code',
                        'desc',
                        'qty',
                        'unit_price',
                        'account_id_coa',
                        'tax_rate',
                        'paket_tracking_uuid',
                        'divisi_travel_tracking_uuid',
                        'amount',
                        'total_base',
                        'updated_at',
                    ]
                );

                // ────────────────────────────────────────────────────────────
                // ✅ 5. Upsert TransactionAllCoa untuk AUTHORISED/PAID
                // ────────────────────────────────────────────────────────────
                $status = $inv['Status'] ?? null;

                if ($status === 'AUTHORISED' || $status === 'PAID') {
                    $detailUuids = collect($batchDetails)->pluck('uuid_detail')->toArray();

                    $savedDetails = DBill::whereIn('uuid_detail', $detailUuids)
                        ->get()
                        ->keyBy('uuid_detail');

                    foreach ($batchDetails as $detail) {
                        if (empty($detail['account_id_coa'])) {
                            continue;
                        }

                        $saved = $savedDetails[$detail['uuid_detail']] ?? null;
                        if (!$saved) {
                            continue;
                        }

                        TransactionAllCoa::updateOrCreate(
                            ['uuid_detail' => $saved->uuid_detail],
                            [
                                'date_transaction' => $issueDate,
                                'uuid_coa' => $detail['account_id_coa'],
                                'reference' => $inv['Reference'] ?? '-',
                                'is_speend' => 1,
                                'nominal' => $saved->amount,
                                'uuid_detail' => $saved->uuid_detail,
                                'code_curr' => $currencyCode,
                                'nominal_currency' => $currencyCode == 'SAR' ? number_format(1 / $currencyRate, 2, '.', '') : 1,
                                'base_nominal' => $saved->total_base,
                            ]
                        );
                    }
                }
            }
        }, 5); // Max 5 attempts sebelum fail
    }

    // ================================================================
    // TRACKING CATEGORY RESOLVER
    // ================================================================

    private function resolveTrackingUuid(string $parentName, string $optionName): ?string
    {
        $cacheKey = $parentName . '::' . $optionName;

        if (array_key_exists($cacheKey, $this->trackingCache)) {
            return $this->trackingCache[$cacheKey];
        }

        $kategori = DB::table('tracking_categories')
            ->where('name_parent_category', $parentName)
            ->whereJsonContains('lines_category', ['item_name_category' => $optionName])
            ->first();

        if (!$kategori) {
            return $this->trackingCache[$cacheKey] = null;
        }

        $lines = collect(json_decode($kategori->lines_category, true));
        $item = $lines->firstWhere('item_name_category', $optionName);

        return $this->trackingCache[$cacheKey] = ($item['item_uuid_category'] ?? null);
    }

    // ================================================================
    // XERO API
    // ================================================================

    private function fetchPage(string $accessToken, string $tenantId, int $page): ?Response
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Xero-Tenant-Id' => $tenantId,
                'Accept' => 'application/json',
            ])->timeout(25)->get('https://api.xero.com/api.xro/2.0/Invoices', [
                        'Statuses' => 'DRAFT,SUBMITTED,AUTHORISED,PAID',
                        'where' => 'Type=="ACCPAY"',
                        'order' => 'Date DESC',
                        'page' => $page,
                        'unitdp' => 4,
                    ]);

            if (!$response->successful() && $response->status() !== 429) {
                Log::error("[SyncBillJob] Fetch page $page gagal [{$response->status()}]: " . substr($response->body(), 0, 300));
            }

            return $response;

        } catch (\Exception $e) {
            Log::error("[SyncBillJob] Exception fetch page $page: " . $e->getMessage());
            return null;
        }
    }

    private function parseXeroDate(?string $dateStr): ?string
    {
        if (!$dateStr) {
            return null;
        }

        if (strpos($dateStr, 'T') !== false || strpos($dateStr, '-') !== false) {
            try {
                return Carbon::parse($dateStr)->format('Y-m-d');
            } catch (\Exception $e) {
                Log::warning("[parseXeroDate] Gagal parse tanggal ISO: $dateStr");
                return null;
            }
        }

        if (preg_match('/\/Date\((\d+)/', $dateStr, $matches)) {
            return Carbon::createFromTimestampMs((int) $matches[1])->format('Y-m-d');
        }

        Log::warning("[parseXeroDate] Format tanggal tidak dikenali: $dateStr");
        return null;
    }
}