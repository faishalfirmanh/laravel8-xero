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
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncXeroInvoiceJob implements ShouldQueue
{
    //update 26 Juni 2026
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, ConfigRefreshXero;

    public int $timeout = 700;

    // Xero hanya kembalikan 100 invoice per halaman
    private const PER_PAGE = 100;

    // Berhenti & release job kalau sisa kuota per-menit sudah sekritis ini
    private const MIN_REM_THRESHOLD = 5;

    // Mulai memperlambat (proaktif) begitu sisa kuota per-menit di bawah ini,
    // supaya tidak nabrak ke MIN_REM_THRESHOLD / 429
    private const SLOWDOWN_THRESHOLD = 15;

    private const THROTTLE_PAGE_US = 400_000; // 400ms antar halaman invoice
    private const THROTTLE_PAYMENT_US = 200_000; // 200ms antar payment fetch
    private const THROTTLE_SLOW_US = 1_000_000; // 1s extra saat sisa kuota menipis

    private array $tokenData;
    private string $jobId;

    /** @var GlobalService */
    protected $service_global;

    /**
     * Flag global: begitu true, SEMUA pemanggilan ke Xero (baik fetch invoice
     * list maupun fetch payment) langsung dihentikan di titik manapun dia
     * sedang berjalan (loop invoice, loop payment, dst), lalu job di-release.
     *
     * Ini mencegah job "kebelet" tetap lanjut request padahal kuota sudah kritis,
     * yang sebelumnya jadi penyebab utama 429 beruntun.
     */
    private bool $shouldRelease = false;
    private int $releaseAfterSecs = 60;

    /**
     * In-memory cache untuk tracking category UUID (Nama Paket / Divisi).
     * Tanpa ini, setiap line item dengan tracking akan query DB sendiri-sendiri.
     *
     * @var array<string, string|null>
     */
    private array $trackingCache = [];

    /**
     * FIX #1 — Cache tenant ID untuk SELURUH job run.
     *
     * Sebelumnya getDetailPayment() memanggil $this->getTenantId($accessToken)
     * lagi untuk SETIAP payment. Kalau getTenantId() melakukan request ke Xero
     * (misal endpoint /connections), maka tiap payment diam-diam membakar 1
     * request EKSTRA ke Xero di luar request GET /Payments/{id} itu sendiri.
     * Untuk invoice yang punya banyak payment, ini bisa melipatgandakan jumlah
     * request ke Xero per job run — salah satu penyebab tersembunyi job cepat
     * kena limit. Tenant ID tidak berubah selama 1 job run, jadi cukup diambil
     * sekali lalu dipakai ulang.
     */
    private ?string $cachedTenantId = null;

    public function __construct(array $tokenData, string $jobId)
    {
        $this->tokenData = $tokenData;
        $this->jobId = $jobId;
        $this->service_global = new GlobalService();
    }

    /**
     * Gunakan retryUntil() bukan $tries.
     *
     * Xero bisa mengirim Retry-After hingga puluhan ribu detik (reset limit
     * harian). Dengan $tries kecil, job akan permanent-failed jauh sebelum
     * kuota benar-benar reset. retryUntil() membiarkan job tetap hidup di
     * antrian selama 26 jam sehingga otomatis lanjut begitu kuota pulih.
     */
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
            $accessToken = $this->tokenData['access_token'];
            $tenantId = $this->getTenantIdCached($accessToken);

            // ── FIX #2 — Resume dari page terakhir, JANGAN mulai dari page 1 ──
            //
            // Job ini didesain untuk di-release() & otomatis di-retry sampai
            // 26 jam (lihat retryUntil()) setiap kali kuota Xero kritis/429.
            // SEBELUM fix ini, setiap kali job lanjut lagi (habis release),
            // $page selalu di-reset ke 1 — artinya job mengulang fetch invoice
            // dari awal, padahal page-page sebelumnya sudah pernah diambil &
            // disimpan. Itu salah satu penyebab terbesar job TERUS-MENERUS
            // kena limit Xero: kuota habis lagi untuk "mengejar" progress yang
            // sebenarnya sudah pernah dicapai, sebelum sempat menyentuh data
            // baru.
            //
            // job_id yang sama dipertahankan oleh queue setiap release()
            // (job di-serialize ulang dengan properti yang sama), jadi row
            // SyncJobStatus dengan job_id ini akan ketemu lagi di run
            // berikutnya. firstOrCreate() dipakai supaya tetap aman kalau ini
            // run pertama (row belum ada).
            // Catatan: sengaja TIDAK pakai firstOrCreate([...], [...]) di sini.
            // firstOrCreate() membuat row baru lewat Eloquent mass-assignment
            // (create()), yang diam-diam gagal menyimpan kolom apa pun yang
            // tidak ada di $fillable model SyncJobStatus. Set atribut satu-satu
            // + save() di bawah ini SELALU jalan terlepas dari $fillable —
            // sama seperti ::where(...)->update() yang dipakai kode asli.
            $jobStatus = SyncJobStatus::where('job_id', $this->jobId)->first();

            if (!$jobStatus) {
                $jobStatus = new SyncJobStatus();
                $jobStatus->job_id = $this->jobId;
                $jobStatus->job_type = 'SyncXeroInvoiceJob';
                $jobStatus->total_synced = 0;
                $jobStatus->total_pages = 0;
                $jobStatus->save();
            }

            $page = $jobStatus->total_pages > 0 ? (int) $jobStatus->total_pages : 1;
            $totalSynced = (int) ($jobStatus->total_synced ?? 0);

            if ($page > 1) {
                Log::info(
                    "[SyncXeroInvoiceJob][$this->jobId] Resume dari page $page " .
                    "(totalSynced sebelumnya: $totalSynced). Tidak mulai dari page 1 lagi."
                );
            }

            Log::info("[SyncXeroInvoiceJob][$this->jobId] Mulai sync invoice...");

            do {
                $response = $this->fetchPage($accessToken, $tenantId, $page);

                if ($response === null) {
                    throw new \RuntimeException("fetchPage() mengembalikan null pada page $page (exception jaringan).");
                }

                // ── 429 Too Many Requests ───────────────────────────────────
                if ($response->status() === 429) {
                    $retryAfter = (int) ($response->header('Retry-After') ?? 60);
                    Log::warning("[SyncXeroInvoiceJob][$this->jobId] Rate limited (429) di page $page. Re-queue {$retryAfter}s.");
                    $this->triggerRelease($retryAfter);
                    break;
                }

                if (!$response->successful()) {
                    throw new \RuntimeException(
                        "Gagal fetch halaman $page. HTTP {$response->status()}: " . substr($response->body(), 0, 300)
                    );
                }

                // ── Guard kuota + catat pemakaian via service_global ────────
                $this->guardRateLimit($response, "invoice-list page $page");
                if ($this->shouldRelease) {
                    break;
                }

                $invoices = $response->json('Invoices') ?? [];

                foreach ($invoices as $inv) {
                    // Cek flag SEBELUM proses invoice berikutnya — kalau payment
                    // fetch invoice sebelumnya sudah memicu release, jangan lanjut.
                    if ($this->shouldRelease) {
                        break;
                    }

                    $this->processInvoice($inv);
                    $totalSynced++;
                }

                SyncJobStatus::where('job_id', $this->jobId)->update([
                    'total_synced' => $totalSynced,
                    'total_pages' => $page,
                ]);

                Log::info("[SyncXeroInvoiceJob][$this->jobId] Page $page selesai. Total tersimpan: $totalSynced");

                if ($this->shouldRelease) {
                    break;
                }

                $hasNextPage = count($invoices) === self::PER_PAGE;
                $page++;

                if ($hasNextPage) {
                    usleep(self::THROTTLE_PAGE_US);
                }

            } while ($hasNextPage);

            // ── Kalau ada sinyal release di titik manapun, requeue job ──────
            if ($this->shouldRelease) {
                Log::warning(
                    "[SyncXeroInvoiceJob][$this->jobId] Kuota Xero kritis. " .
                    "Job di-release, lanjut otomatis setelah {$this->releaseAfterSecs}s. " .
                    "Progress tersimpan: $totalSynced invoice."
                );
                $this->release($this->releaseAfterSecs);
                return;
            }

            Log::info("[SyncXeroInvoiceJob][$this->jobId] Selesai. Total invoice: $totalSynced");

        } catch (\Exception $e) {
            Log::error("[SyncXeroInvoiceJob][$this->jobId] Error: " . $e->getMessage());
            throw $e;
        }
    }

    // ================================================================
    // TENANT ID (cached per job run — lihat $cachedTenantId di atas)
    // ================================================================

    private function getTenantIdCached(string $accessToken): string
    {
        if ($this->cachedTenantId === null) {
            $this->cachedTenantId = $this->getTenantId($accessToken);
        }

        return $this->cachedTenantId;
    }

    // ================================================================
    // RATE LIMIT GUARD (terpusat — dipakai invoice list & payment fetch)
    // ================================================================

    /**
     * Baca header limit dari response Xero, catat ke service_global, dan
     * tentukan apakah job harus berhenti total (set $this->shouldRelease).
     *
     * Juga melakukan proactive slowdown: makin dekat ke limit, makin lambat
     * — supaya tidak tiba-tiba nabrak 429 di tengah jalan.
     */
    private function guardRateLimit(Response $response, string $context): void
    {
        $minRemHeader = $response->header('X-MinLimit-Remaining');
        $dayRemHeader = $response->header('X-DayLimit-Remaining');

        // Header tidak selalu ada di semua response — jangan asumsikan 0.
        if ($minRemHeader === null || $minRemHeader === '') {
            return;
        }

        $minRem = (int) $minRemHeader;
        $dayRem = (int) ($dayRemHeader ?? 0);

        // Catat pemakaian kuota ke service terpisah (sudah ada di kode asal)
        $this->service_global->requestCalculationXero($minRem, $dayRem);

        Log::info("[SyncXeroInvoiceJob][$this->jobId] [$context] MinRem: $minRem | DayRem: $dayRem");

        if ($minRem <= self::MIN_REM_THRESHOLD) {
            Log::warning("[SyncXeroInvoiceJob][$this->jobId] Kuota kritis ($minRem/menit) di $context.");
            $this->triggerRelease(65); // tunggu 1 window menit + buffer
            return;
        }

        // ── Proactive slowdown: makin dekat limit, makin lambat ────────────
        if ($minRem <= self::SLOWDOWN_THRESHOLD) {
            usleep(self::THROTTLE_SLOW_US);
        }
    }

    /**
     * Set sinyal release. Dipanggil dari mana saja yang mendeteksi kuota kritis.
     * Pakai nilai terbesar kalau dipanggil berkali-kali dalam 1 run.
     */
    private function triggerRelease(int $seconds): void
    {
        $this->shouldRelease = true;
        $this->releaseAfterSecs = max($this->releaseAfterSecs, $seconds);
    }

    // ================================================================
    // PAYMENT SYNC
    // ================================================================

    /**
     * Sync satu payment — skip kalau sudah pernah tersimpan ATAU job sedang
     * dalam proses berhenti karena kuota kritis.
     *
     * INI YANG SEBELUMNYA TIDAK DIPAKAI — processInvoice() memanggil
     * getDetailPayment() langsung tanpa dedup, sehingga SETIAP sync ulang
     * (cron harian) menarik ulang SEMUA payment dari Xero walau sudah ada
     * di DB. Ini salah satu penyebab terbesar kuota cepat habis.
     */

    public function getDetailPayment(string $idPayment, ?int $knownParentId = null): void
    {
        if ($this->shouldRelease) {
            return;
        }

        $accessToken = $this->tokenData['access_token'];
        $tenantId = $this->getTenantIdCached($accessToken);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Xero-Tenant-Id' => $tenantId,
            'Accept' => 'application/json',
        ])->timeout(25)->get("https://api.xero.com/api.xro/2.0/Payments/$idPayment");

        if ($response->status() === 429) {
            $retryAfter = (int) ($response->header('Retry-After') ?? 60);
            Log::warning("[getDetailPayment] Rate limited (429) payment $idPayment. Release {$retryAfter}s.");
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
            Log::warning("[getDetailPayment] Payment $idPayment tidak ditemukan/kosong di response Xero, dilewati.");
            return;
        }

        $amount = (float) ($payment['Amount'] ?? 0);
        $accountCode = data_get($payment, 'Account.Code');
        $bankName = data_get($payment, 'Account.Name');
        $date = $this->parseXeroDate($payment['Date'] ?? null);
        $invoiceUuid = data_get($payment, 'Invoice.InvoiceID');
        $invoiceNumber = data_get($payment, 'Invoice.InvoiceNumber');
        $refPayment = data_get($payment, 'Reference');//

        // Pakai parent id yang sudah diketahui (dilempar dari processInvoice)
        // dulu kalau ada — hindari query tambahan ke InvoicesAllFromXero.
        $idParentInv = $knownParentId
            ?? ($invoiceUuid ? InvoicesAllFromXero::where('invoice_uuid', $invoiceUuid)->value('id') : null);

        $this->insertToDb($invoiceNumber, $bankName, $idPayment, $amount, $accountCode, $date, $refPayment, $idParentInv);

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
        ?int $idParentInv
    ): void {
        if (!$accountCode) {
            Log::warning("[insertToDb] AccountCode kosong. Payment {$paymentUuid} dilewati. Invoice: $invNumber");
            return;
        }

        $findBank = BankXero::where('code', $accountCode)->first();

        if (!$findBank) {
            Log::warning(
                "[insertToDb] Kode akun bank tidak ditemukan: '{$accountCode}'. " .
                "Payment {$paymentUuid} dilewati. Nama bank: {$namaBank}. Invoice: $invNumber"
            );
            return;
        }
        // updateOrCreate → idempoten, aman saat job di-retry/release/cron ulang
        TransactionNominalBankAccount::updateOrCreate(
            ['payment_uuid' => $paymentUuid],
            [
                'uuid_bank' => $findBank->id,
                'nominal_receive' => $amount,
                'created_by' => 1,
                'date_transaction' => $date,
                'nominal_spend' => 0,
                'nominal_transfer' => 0,
                'reference_detail' => $refDetail,
                'id_parent_invoice' => $idParentInv,
            ]
        );
        //}

    }

    // ================================================================
    // INVOICE PROCESSING
    // ================================================================

    private function processInvoice(array $inv): void
    {
        $lineItems = $inv['LineItems'] ?? [];
        $firstLine = $lineItems[0] ?? [];
        $issueDate = $this->parseXeroDate($inv['DateString'] ?? $inv['Date'] ?? null);
        $dueDate = $this->parseXeroDate($inv['DueDateString'] ?? $inv['DueDate'] ?? null);
        $contactId = data_get($inv, 'Contact.ContactID');

        $findContact = DataJamaahXero::where('uuid_contact', $contactId)->value('id') ?? 1;

        // ── 1. Upsert parent invoice DULU ───────────────────────────────
        // PENTING: ini harus jalan SEBELUM sync payment, supaya saat
        // getDetailPayment() mencari id_parent_invoice, baris invoice-nya
        // sudah ada di DB. Di kode sebelumnya payment disync duluan →
        // id_parent_invoice selalu null di sync pertama kali.
        InvoicesAllFromXero::upsert(
            [
                [
                    'invoice_uuid' => $inv['InvoiceID'],
                    'invoice_number' => $inv['InvoiceNumber'] ?? null,
                    'invoice_amount' => $inv['AmountPaid'] ?? 0,
                    'invoice_total' => $inv['Total'] ?? 0,
                    'less_nominal' => $inv['AmountDue'] ?? 0,
                    'issue_date' => $issueDate,
                    'due_date' => $dueDate,
                    'status' => $inv['Status'] ?? null,
                    'uuid_contact' => $contactId,
                    'contact_name' => data_get($inv, 'Contact.Name'),
                    'contact_id' => $findContact,
                    'uuid_proudct_and_service' => $firstLine['ItemID'] ?? null,
                    'item_name' => $firstLine['Description'] ?? null,
                    'reference' => $inv['Reference'] ?? null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            ],
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

        // ── 2. Sync payment (dengan dedup + parent id yang sudah ada) ──────
        $payments = $inv['Payments'] ?? [];

        if (!empty($payments)) {
            foreach ($payments as $paymentRow) {
                if ($this->shouldRelease) {
                    break; // kuota kritis terdeteksi — stop, jangan hit Xero lagi
                }

                $paymentId = $paymentRow['PaymentID'] ?? null;
                if (!$paymentId) {
                    continue;
                }

                $alreadySynced = TransactionNominalBankAccount::where('payment_uuid', $paymentId)->exists();
                if ($alreadySynced) {
                    continue; // sudah ada, tidak perlu hit Xero
                }

                $this->getDetailPayment($paymentId, $parentId);
            }
        }

        if (!$parentId || empty($lineItems) || $this->shouldRelease) {
            return;
        }

        // ── 3. Pre-load COA dan Item SEKALI sebelum loop — hindari N+1 ─────
        $accountCodes = collect($lineItems)->pluck('AccountCode')->filter()->unique()->values()->toArray();

        $itemCodes = collect($lineItems)
            ->filter(fn($l) => isset($l['Item']['Code']))
            ->map(fn($l) => $l['Item']['Code'])
            ->unique()
            ->values()
            ->toArray();

        $coaMap = Coa::whereIn('code', $accountCodes)->pluck('id', 'code')->toArray();
        $itemMap = ItemsPaketAllFromXero::whereIn('code', $itemCodes)->pluck('id', 'code')->toArray();

        // ── 4. Build batch line items — semua lookup dari array, tanpa query ──
        $batchDetails = [];

        foreach ($lineItems as $line) {
            $paketUuid = null;
            $divisiUuid = null;

            foreach ($line['Tracking'] ?? [] as $track) {
                // PHP 7.4 compatible: pakai strpos(), str_contains() PHP 8.0+ only
                $categoryName = strtolower($track['Name'] ?? '');
                $optionName = $track['Option'] ?? '';

                if (strpos($categoryName, 'nama paket') !== false) {
                    $paketUuid = $this->resolveTrackingUuid('Nama Paket', $optionName);
                } elseif (strpos($categoryName, 'divisi') !== false) {
                    $divisiUuid = $this->resolveTrackingUuid('Divisi', $optionName);
                }
            }

            $coaId = isset($line['AccountCode']) ? ($coaMap[$line['AccountCode']] ?? null) : null;
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
                'uuid_detail_inv' => $this->service_global->generateUniqueString(),
                'paket_tracking_uuid' => $paketUuid,
                'divisi_travel_tracking_uuid' => $divisiUuid,
                'desc' => $line['Description'] ?? null,
                'updated_at' => now(),
                'created_at' => now(),
            ];
        }

        if (empty($batchDetails)) {
            return;
        }

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
                'uuid_detail_inv',
            ]
        );

        // ── 5. Upsert TransactionAllCoa — hanya untuk invoice AUTHORISED/PAID ──
        $status = $inv['Status'] ?? null;

        if ($status === 'AUTHORISED' || $status === 'PAID') {
            $lineItemUuids = collect($batchDetails)->pluck('line_item_uuid')->toArray();

            $savedDetails = ItemDetailInvoices::whereIn('line_item_uuid', $lineItemUuids)
                ->get()
                ->keyBy('line_item_uuid');

            foreach ($batchDetails as $detail) {
                if (empty($detail['coa_id'])) {
                    continue;
                }

                $saved = $savedDetails[$detail['line_item_uuid']] ?? null;
                if (!$saved) {
                    continue;
                }

                TransactionAllCoa::firstOrCreate(
                    ['uuid_detail' => $saved->uuid_detail_inv],
                    [
                        'date_transaction' => $issueDate,
                        'uuid_coa' => $detail['coa_id'],
                        'reference' => $inv['Reference'] ?? '-',
                        'is_speend' => 0,
                        'nominal' => $saved->total_amount_each_row,
                        'uuid_detail' => $saved->uuid_detail_inv,
                    ]
                );
            }
        }
    }

    // ================================================================
    // TRACKING CATEGORY RESOLVER (dengan in-memory cache)
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
    // XERO API — FETCH PAGE
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
                        'Type' => 'ACCREC',
                        'order' => 'Date DESC',
                        'page' => $page,
                        'unitdp' => 4,
                    ]);

            if (!$response->successful() && $response->status() !== 429) {
                Log::error("[SyncXeroInvoiceJob] Fetch page $page gagal [{$response->status()}]: " . substr($response->body(), 0, 300));
            }

            return $response;

        } catch (\Exception $e) {
            Log::error("[SyncXeroInvoiceJob] Exception fetch page $page: " . $e->getMessage());
            return null;
        }
    }

    // ================================================================
    // DATE PARSER
    // ================================================================

    private function parseXeroDate(?string $dateStr): ?string
    {
        if (!$dateStr) {
            return null;
        }

        // Format ISO: "2024-01-15T00:00:00" — PHP 7.4 compatible (strpos, bukan str_contains)
        if (strpos($dateStr, 'T') !== false || strpos($dateStr, '-') !== false) {
            try {
                return Carbon::parse($dateStr)->format('Y-m-d');
            } catch (\Exception $e) {
                Log::warning("[parseXeroDate] Gagal parse tanggal ISO: $dateStr");
                return null;
            }
        }

        // Format epoch: "/Date(1704067200000+0000)/"
        if (preg_match('/\/Date\((\d+)/', $dateStr, $matches)) {
            return Carbon::createFromTimestampMs((int) $matches[1])->format('Y-m-d');
        }

        Log::warning("[parseXeroDate] Format tanggal tidak dikenali: $dateStr");
        return null;
    }
}