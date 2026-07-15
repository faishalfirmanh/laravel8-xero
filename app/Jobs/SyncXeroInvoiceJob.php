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
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ====================================================================
 * UPDATE 30 Juni 2026 — Perbaikan akar penyebab sering kena rate limit
 * ====================================================================
 *
 * HASIL ANALISA root cause sebelumnya MASIH sering kena limit walau
 * sudah ada FIX #1 & #2 (cache tenant id, resume page), karena:
 *
 *  A) N+1 REQUEST PAYMENT (penyebab terbesar)
 *     processInvoice() memanggil getDetailPayment() SATU KALI PER
 *     PAYMENT (GET /Payments/{id}). Untuk 100 invoice/halaman dengan
 *     rata-rata 2-3 payment/invoice, itu 200-300 request Xero EKSTRA
 *     hanya untuk 1 halaman invoice. Limit per-menit Xero cuma 60
 *     request, jadi pasti jebol meski sudah ada dedup
 *     ($alreadySynced) — karena pas testing/sync pertama kali, hampir
 *     semua payment memang belum ada di DB, jadi dedup-nya tidak
 *     membantu banyak.
 *
 *  B) PACING REAKTIF, BUKAN PROAKTIF, DAN TERLALU CEPAT
 *     THROTTLE_PAYMENT_US lama = 200ms antar payment = maks 5
 *     request/detik = 300 request/menit, padahal limit asli Xero
 *     cuma 60/menit. Guard rate limit baru "ngerem" SETELAH baca
 *     header X-MinLimit-Remaining dari response sebelumnya — di titik
 *     itu kuota menit ini sudah nyaris habis, sehingga 429 sering
 *     baru "ketahuan" setelah beberapa request gagal duluan.
 *
 *  C) TIDAK ADA PROTEKSI JOB DOBEL JALAN BERSAMAAN
 *     Kalau saat testing job_id yang sama ke-dispatch 2x (klik tombol
 *     sync dobel, worker + tinker bersamaan, dst), dua instance job
 *     berebut kuota Xero yang SAMA tanpa saling tahu — masing-masing
 *     instance terlihat "aman" dari sisi pacing-nya sendiri, tapi
 *     totalnya tetap nabrak limit.
 *
 * PERBAIKAN:
 *
 *  FIX #3 — Payment sync diganti dari "1 request per payment ID" jadi
 *  BULK FETCH via GET /Payments yang di-page (sama seperti pola
 *  invoice, 100 payment per request). Ini memotong ratusan request
 *  jadi tinggal beberapa request saja per job run. Disimpan sebagai
 *  PHASE terpisah ("payments") yang resumable sendiri, dijalankan
 *  SETELAH phase invoice selesai (supaya parent invoice id sudah ada
 *  di DB untuk SEMUA invoice, bukan cuma yang sudah lewat saat itu).
 *
 *  FIX #4 — Rate limiter PROAKTIF berbasis Cache (waitForXeroSlot):
 *  dipanggil SEBELUM setiap request ke Xero (bukan sesudahnya), jaga
 *  jarak minimum antar request supaya laju request dari awal sudah
 *  di bawah limit asli Xero (~54 request/menit, ada margin aman dari
 *  60/menit). Bekerja lintas proses karena disimpan di Cache, bukan
 *  in-memory per-instance — jadi tetap aman walau ada >1 job/worker
 *  yang menyentuh tenant Xero yang sama.
 *
 *  FIX #5 — Implement ShouldBeUnique berdasarkan $jobId, cegah 2
 *  instance job dengan job_id sama berjalan bersamaan. release()
 *  tetap aman dipakai bareng ShouldBeUnique karena release() bekerja
 *  di level queue driver, bukan lewat Bus::dispatch() lagi, sehingga
 *  tidak kena cek unique lock dari dispatch awal.
 *  PERLU: cache driver yang aktif harus bisa menyimpan key dengan TTL
 *  (file/database/redis semua bisa). Kalau mau atomic lock yang lebih
 *  kuat lintas worker, disarankan pindah ke driver 'redis' atau
 *  'database' untuk Cache.
 *
 *  FIX #6 — Guard day-limit juga (X-DayLimit-Remaining), tidak cuma
 *  per-menit. Kalau kuota harian sudah kritis, job di-release sampai
 *  reset harian (~tengah malam UTC), bukan cuma nunggu 60 detik.
 *
 * MIGRATION YANG DIPERLUKAN (lihat file migration terpisah):
 *   sync_job_statuses perlu 3 kolom tambahan:
 *     - current_phase        VARCHAR, default 'invoices'
 *     - total_pages_payment  INTEGER, default 0
 *     - total_payment_synced INTEGER, default 0
 *
 *   transaction_nominal_bank_accounts.payment_uuid SEBAIKNYA punya
 *   UNIQUE INDEX di level database (upsert() butuh constraint asli,
 *   beda dengan updateOrCreate() yang dipakai kode lama).
 * ====================================================================
 */
class SyncXeroInvoiceJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, ConfigRefreshXero;

    public int $timeout = 700;

    // Xero hanya kembalikan 100 invoice/payment per halaman
    private const PER_PAGE = 100;
    private const PAYMENT_PER_PAGE = 100;

    // Berhenti & release job kalau sisa kuota per-menit sudah sekritis ini
    private const MIN_REM_THRESHOLD = 5;

    // Mulai memperlambat (proaktif tambahan) begitu sisa kuota per-menit
    // di bawah ini — lapisan KEDUA di atas pacing utama (FIX #4).
    private const SLOWDOWN_THRESHOLD = 15;

    // FIX #6 — kalau sisa kuota HARIAN sudah sekritis ini, release sampai
    // reset harian, jangan cuma nunggu 60 detik (karena pasti masih kena
    // 429 lagi kalau cuma nunggu sebentar).
    private const DAY_REM_CRITICAL = 50;

    private const THROTTLE_SLOW_US = 1_000_000; // 1s extra saat sisa kuota menipis (lapisan kedua)

    // FIX #4 — jarak minimum PROAKTIF antar SEMUA request ke Xero
    // (invoice page ATAUPUN payment page), dicek SEBELUM request dikirim.
    // 1.1s antar request → maksimal ~54 request/menit, aman di bawah
    // limit asli Xero (60 request/menit per tenant).
    private const XERO_MIN_INTERVAL_US = 1_100_000;
    private const XERO_PACING_CACHE_PREFIX = 'xero_api:last_request_at:';

    private array $tokenData;
    private string $jobId;

    /** @var GlobalService */
    protected $service_global;

    /**
     * Flag global: begitu true, SEMUA pemanggilan ke Xero (baik fetch
     * invoice list, payment list, dst) langsung dihentikan di titik
     * manapun dia sedang berjalan, lalu job di-release.
     */
    private bool $shouldRelease = false;
    private int $releaseAfterSecs = 60;

    /**
     * In-memory cache untuk tracking category UUID (Nama Paket / Divisi).
     */
    private array $trackingCache = [];

    /**
     * FIX #1 — Cache tenant ID untuk SELURUH job run (tetap dipertahankan).
     */
    private ?string $cachedTenantId = null;

    /**
     * FIX #5 — ShouldBeUnique: job dengan job_id yang sama tidak boleh
     * ke-dispatch dobel selagi salah satu instance-nya masih "hidup" di
     * antrian (termasuk selama menunggu release()).
     * Diset sama dengan jangka waktu retryUntil() di bawah.
     */
    public int $uniqueFor = 26 * 3600;

    public function uniqueId(): string
    {
        return $this->jobId;
    }

    public function __construct(array $tokenData, string $jobId)
    {
        $this->tokenData = $tokenData;
        $this->jobId = $jobId;
        $this->service_global = new GlobalService();
    }

    /**
     * Gunakan retryUntil() bukan $tries — Xero bisa kirim Retry-After
     * sampai puluhan ribu detik (reset limit harian). retryUntil()
     * membiarkan job tetap hidup di antrian selama 26 jam.
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


            $jobStatus = SyncJobStatus::where('job_id', $this->jobId)->first();

            if (!$jobStatus) {
                $jobStatus = new SyncJobStatus();
                $jobStatus->job_id = $this->jobId;
                $jobStatus->job_type = 'SyncXeroInvoiceJob';
                $jobStatus->total_synced = 0;
                $jobStatus->total_pages = 0;
                $jobStatus->total_payment_synced = 0;
                $jobStatus->total_pages_payment = 0;
                $jobStatus->current_phase = 'invoices';
                $jobStatus->save();
            }

            // Default 'invoices' untuk row LAMA (sebelum migration ini ada)
            // yang current_phase-nya masih NULL.
            $phase = $jobStatus->current_phase ?: 'invoices';

            if ($phase === 'invoices') {
                Log::info("[SyncXeroInvoiceJob][$this->jobId] === PHASE: invoices ===");

                $this->syncInvoicesPhase($accessToken, $tenantId, $jobStatus);

                if ($this->shouldRelease) {
                    Log::warning(
                        "[SyncXeroInvoiceJob][$this->jobId] Release saat phase invoices. " .
                        "Lanjut otomatis setelah {$this->releaseAfterSecs}s."
                    );
                    $this->release($this->releaseAfterSecs);
                    return;
                }

                $jobStatus->current_phase = 'payments';
                $jobStatus->save();
                $phase = 'payments';
            }

            if ($phase === 'payments') {
                Log::info("[SyncXeroInvoiceJob][$this->jobId] === PHASE: payments ===");

                $this->syncPaymentsPhase($accessToken, $tenantId, $jobStatus);

                if ($this->shouldRelease) {
                    Log::warning(
                        "[SyncXeroInvoiceJob][$this->jobId] Release saat phase payments. " .
                        "Lanjut otomatis setelah {$this->releaseAfterSecs}s."
                    );
                    $this->release($this->releaseAfterSecs);
                    return;
                }

                $jobStatus->current_phase = 'done';
                $jobStatus->save();
            }

            Log::info(
                "[SyncXeroInvoiceJob][$this->jobId] Selesai semua phase. " .
                "Invoice: {$jobStatus->total_synced} | Payment: {$jobStatus->total_payment_synced}"
            );

        } catch (\Exception $e) {
            Log::error("[SyncXeroInvoiceJob][$this->jobId] Error: " . $e->getMessage());
            throw $e;
        }
    }

    // ================================================================
    // PHASE 1 — SYNC INVOICE (list + line items, TANPA fetch payment
    // satu-satu — payment sekarang murni di PHASE 2)
    // ================================================================

    private function syncInvoicesPhase(string $accessToken, string $tenantId, SyncJobStatus $jobStatus): void
    {
        $page = $jobStatus->total_pages > 0 ? (int) $jobStatus->total_pages : 1;
        $totalSynced = (int) ($jobStatus->total_synced ?? 0);

        if ($page > 1) {
            Log::info(
                "[SyncXeroInvoiceJob][$this->jobId] Resume invoice dari page $page " .
                "(totalSynced sebelumnya: $totalSynced)."
            );
        }

        do {
            $response = $this->fetchPage($accessToken, $tenantId, $page);

            if ($response === null) {
                throw new \RuntimeException("fetchPage() mengembalikan null pada page $page (exception jaringan).");
            }

            if ($response->status() === 429) {
                $retryAfter = (int) ($response->header('Retry-After') ?? 60);
                Log::warning("[SyncXeroInvoiceJob][$this->jobId] Rate limited (429) di invoice page $page. Re-queue {$retryAfter}s.");
                $this->triggerRelease($retryAfter);
                break;
            }

            if (!$response->successful()) {
                throw new \RuntimeException(
                    "Gagal fetch invoice halaman $page. HTTP {$response->status()}: " . substr($response->body(), 0, 300)
                );
            }

            $this->guardRateLimit($response, "invoice-list page $page");
            if ($this->shouldRelease) {
                break;
            }

            $invoices = $response->json('Invoices') ?? [];

            foreach ($invoices as $inv) {
                $this->processInvoice($inv);
                $totalSynced++;
            }

            $jobStatus->total_synced = $totalSynced;
            $jobStatus->total_pages = $page;
            $jobStatus->save();

            Log::info("[SyncXeroInvoiceJob][$this->jobId] Invoice page $page selesai. Total tersimpan: $totalSynced");

            if ($this->shouldRelease) {
                break;
            }

            $hasNextPage = count($invoices) === self::PER_PAGE;
            $page++;

        } while ($hasNextPage);

        if (!$this->shouldRelease) {
            Log::info("[SyncXeroInvoiceJob][$this->jobId] Phase invoices selesai. Total invoice: $totalSynced.");
        }
    }

    // ================================================================
    // PHASE 2 — SYNC PAYMENT (BULK, dipaging — BUKAN 1 request/ID)
    // ================================================================

    /**
     * FIX #3 — Sebelumnya processInvoice() panggil getDetailPayment()
     * SATU KALI PER PAYMENT (GET /Payments/{id}). Sekarang diganti
     * fetch SEMUA payment receivable via GET /Payments yang dipaging
     * (100 payment per request), sama persis pola invoice. Payment di
     * Xero sudah membawa object Account (Code) & Invoice (InvoiceID)
     * walau diambil lewat endpoint list — jadi tidak ada informasi
     * yang hilang dibanding fetch satu-satu, hanya jumlah request-nya
     * yang turun drastis (dari O(jumlah payment) jadi O(jumlah
     * payment / 100)).
     *
     * Dijalankan SETELAH phase invoices selesai supaya semua parent
     * invoice (InvoicesAllFromXero) sudah pasti ada di DB untuk
     * di-mapping, termasuk invoice dari halaman manapun.
     */
    private function syncPaymentsPhase(string $accessToken, string $tenantId, SyncJobStatus $jobStatus): void
    {
        $page = $jobStatus->total_pages_payment > 0 ? (int) $jobStatus->total_pages_payment : 1;
        $totalSynced = (int) ($jobStatus->total_payment_synced ?? 0);

        if ($page > 1) {
            Log::info(
                "[SyncXeroInvoiceJob][$this->jobId] Resume payment dari page $page " .
                "(totalSynced sebelumnya: $totalSynced)."
            );
        }

        do {
            $response = $this->fetchPaymentsPage($accessToken, $tenantId, $page);

            if ($response === null) {
                throw new \RuntimeException("fetchPaymentsPage() mengembalikan null pada page $page (exception jaringan).");
            }

            if ($response->status() === 429) {
                $retryAfter = (int) ($response->header('Retry-After') ?? 60);
                Log::warning("[SyncXeroInvoiceJob][$this->jobId] Rate limited (429) di payment page $page. Re-queue {$retryAfter}s.");
                $this->triggerRelease($retryAfter);
                break;
            }

            if (!$response->successful()) {
                throw new \RuntimeException(
                    "Gagal fetch payment halaman $page. HTTP {$response->status()}: " . substr($response->body(), 0, 300)
                );
            }

            $this->guardRateLimit($response, "payment-list page $page");
            if ($this->shouldRelease) {
                break;
            }

            $payments = $response->json('Payments') ?? [];
            $totalSynced += $this->processPaymentsBatch($payments);

            $jobStatus->total_payment_synced = $totalSynced;
            $jobStatus->total_pages_payment = $page;
            $jobStatus->save();

            Log::info("[SyncXeroInvoiceJob][$this->jobId] Payment page $page selesai. Total tersimpan: $totalSynced");

            if ($this->shouldRelease) {
                break;
            }

            $hasNextPage = count($payments) === self::PAYMENT_PER_PAGE;
            $page++;

        } while ($hasNextPage);

        if (!$this->shouldRelease) {
            Log::info("[SyncXeroInvoiceJob][$this->jobId] Phase payments selesai. Total payment: $totalSynced.");
        }
    }

    /**
     * Proses 1 halaman payment (maks 100 baris) jadi batch upsert,
     * tanpa request tambahan ke Xero maupun query N+1 ke DB.
     *
     * @return int jumlah payment yang berhasil di-upsert
     */
    private function processPaymentsBatch(array $payments): int
    {
        if (empty($payments)) {
            return 0;
        }

        // ── Pre-load mapping parent invoice & bank, SEKALI per halaman ──
        $invoiceUuids = collect($payments)
            ->map(fn($p) => data_get($p, 'Invoice.InvoiceID'))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $parentMap = InvoicesAllFromXero::whereIn('invoice_uuid', $invoiceUuids)
            ->pluck('id', 'invoice_uuid')
            ->toArray();

        $accountCodes = collect($payments)
            ->map(fn($p) => data_get($p, 'Account.Code'))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $bankMap = BankXero::whereIn('code', $accountCodes)->pluck('id', 'code')->toArray();

        $rows = [];

        foreach ($payments as $p) {
            $paymentId = $p['PaymentID'] ?? null;
            if (!$paymentId) {
                continue;
            }

            $accountCode = data_get($p, 'Account.Code');
            if (!$accountCode || !isset($bankMap[$accountCode])) {
                Log::warning(
                    "[processPaymentsBatch] Kode akun bank tidak ditemukan/kosong: '" . ($accountCode ?? '-') . "'. " .
                    "Payment {$paymentId} dilewati."
                );
                continue;
            }

            $invoiceUuid = data_get($p, 'Invoice.InvoiceID');

            $rows[] = [
                'payment_uuid' => $paymentId,
                'uuid_bank' => $bankMap[$accountCode],
                'nominal_receive' => (float) ($p['Amount'] ?? 0),
                'created_by' => 1,
                'date_transaction' => $this->parseXeroDate($p['Date'] ?? null),
                'nominal_spend' => 0,
                'nominal_transfer' => 0,
                'reference_detail' => data_get($p, 'Reference'),
                'id_parent_invoice' => $invoiceUuid ? ($parentMap[$invoiceUuid] ?? null) : null,
                'updated_at' => now(),
                'created_at' => now(),
            ];
        }

        if (empty($rows)) {
            return 0;
        }

        TransactionNominalBankAccount::upsert(
            $rows,
            ['payment_uuid'],
            [
                'uuid_bank',
                'nominal_receive',
                'date_transaction',
                'nominal_spend',
                'nominal_transfer',
                'reference_detail',
                'id_parent_invoice',
                'updated_at',
            ]
        );

        return count($rows);
    }

    // ================================================================
    // RATE LIMITER PROAKTIF (FIX #4) — dipanggil SEBELUM tiap request
    // ================================================================

    /**
     * Jaga jarak minimum antar SEMUA request ke Xero (lintas
     * invoice-page & payment-page, lintas job/worker karena disimpan
     * di Cache bukan in-memory). Ini lapisan PERTAMA & utama — guard
     * header-based (guardRateLimit) di bawah cuma jaring pengaman
     * tambahan untuk kasus di luar kendali kita (misal ada proses lain
     * yang juga menyentuh tenant Xero yang sama).
     */
    private function waitForXeroSlot(string $tenantId): void
    {
        $key = self::XERO_PACING_CACHE_PREFIX . $tenantId;
        $last = Cache::get($key);
        $now = microtime(true);

        if ($last !== null) {
            $elapsedUs = (int) (($now - (float) $last) * 1_000_000);
            $waitUs = self::XERO_MIN_INTERVAL_US - $elapsedUs;

            if ($waitUs > 0) {
                usleep($waitUs);
            }
        }

        Cache::put($key, microtime(true), 120);
    }

    // ================================================================
    // TENANT ID (cached per job run)
    // ================================================================

    private function getTenantIdCached(string $accessToken): string
    {
        if ($this->cachedTenantId === null) {
            $this->cachedTenantId = $this->getTenantId($accessToken);
        }

        return $this->cachedTenantId;
    }

    // ================================================================
    // RATE LIMIT GUARD (reaktif — lapisan KEDUA, jaring pengaman)
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

        Log::info("[SyncXeroInvoiceJob][$this->jobId] [$context] MinRem: $minRem | DayRem: $dayRem");

        // FIX #6 — guard kuota HARIAN, jangan cuma per-menit.
        if ($dayRemHeader !== null && $dayRemHeader !== '' && $dayRem <= self::DAY_REM_CRITICAL) {
            $secondsUntilResetUtc = (int) now('UTC')->endOfDay()->diffInSeconds(now('UTC')) + 120;
            Log::warning(
                "[SyncXeroInvoiceJob][$this->jobId] Kuota HARIAN kritis ($dayRem) di $context. " .
                "Release sampai reset harian (~{$secondsUntilResetUtc}s)."
            );
            $this->triggerRelease($secondsUntilResetUtc);
            return;
        }

        if ($minRem <= self::MIN_REM_THRESHOLD) {
            Log::warning("[SyncXeroInvoiceJob][$this->jobId] Kuota kritis ($minRem/menit) di $context.");
            $this->triggerRelease(65); // tunggu 1 window menit + buffer
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
    // PAYMENT TUNGGAL (legacy — dipertahankan untuk pemakaian AD-HOC
    // di luar job ini, misal resync 1 payment dari webhook. TIDAK lagi
    // dipanggil dari alur bulk sync di atas, jadi tidak ikut
    // menyumbang N+1 request.)
    // ================================================================

    public function getDetailPayment(string $idPayment, ?int $knownParentId = null): void
    {
        if ($this->shouldRelease) {
            return;
        }

        $accessToken = $this->tokenData['access_token'];
        $tenantId = $this->getTenantIdCached($accessToken);

        $this->waitForXeroSlot($tenantId);

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
        $refPayment = data_get($payment, 'Reference');

        $idParentInv = $knownParentId
            ?? ($invoiceUuid ? InvoicesAllFromXero::where('invoice_uuid', $invoiceUuid)->value('id') : null);

        $this->insertToDb($invoiceNumber, $bankName, $idPayment, $amount, $accountCode, $date, $refPayment, $idParentInv);
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
    }

    // ================================================================
    // INVOICE PROCESSING (payment loop DIHAPUS dari sini — lihat
    // syncPaymentsPhase() / processPaymentsBatch() di atas, FIX #3)
    // ================================================================

    private function processInvoice(array $inv): void
    {
        $lineItems = $inv['LineItems'] ?? [];
        $firstLine = $lineItems[0] ?? [];
        $issueDate = $this->parseXeroDate($inv['DateString'] ?? $inv['Date'] ?? null);
        $dueDate = $this->parseXeroDate($inv['DueDateString'] ?? $inv['DueDate'] ?? null);
        $contactId = data_get($inv, 'Contact.ContactID');

        $findContact = DataJamaahXero::where('uuid_contact', $contactId)->value('id') ?? 1;

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

        if (!$parentId || empty($lineItems)) {
            return;
        }

        // ── Pre-load COA dan Item SEKALI sebelum loop — hindari N+1 ─────
        $accountCodes = collect($lineItems)->pluck('AccountCode')->filter()->unique()->values()->toArray();

        $itemCodes = collect($lineItems)
            ->filter(fn($l) => isset($l['Item']['Code']))
            ->map(fn($l) => $l['Item']['Code'])
            ->unique()
            ->values()
            ->toArray();

        $coaMap = Coa::whereIn('code', $accountCodes)->pluck('id', 'code')->toArray();
        $itemMap = ItemsPaketAllFromXero::whereIn('code', $itemCodes)->pluck('id', 'code')->toArray();

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
    // XERO API — FETCH INVOICE PAGE
    // ================================================================

    private function fetchPage(string $accessToken, string $tenantId, int $page): ?Response
    {
        $this->waitForXeroSlot($tenantId);

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
                Log::error("[SyncXeroInvoiceJob] Fetch invoice page $page gagal [{$response->status()}]: " . substr($response->body(), 0, 300));
            }

            return $response;

        } catch (\Exception $e) {
            Log::error("[SyncXeroInvoiceJob] Exception fetch invoice page $page: " . $e->getMessage());
            return null;
        }
    }

    // ================================================================
    // XERO API — FETCH PAYMENT PAGE (FIX #3 — bulk, bukan per-ID)
    // ================================================================

    private function fetchPaymentsPage(string $accessToken, string $tenantId, int $page): ?Response
    {
        $this->waitForXeroSlot($tenantId);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Xero-Tenant-Id' => $tenantId,
                'Accept' => 'application/json',
            ])->timeout(25)->get('https://api.xero.com/api.xro/2.0/Payments', [
                        // Hanya payment penerimaan invoice (ACCREC), selaras dengan
                        // Type=ACCREC yang dipakai di fetchPage(). Kalau perlu batasi
                        // periode untuk org dengan histori sangat besar, tambahkan
                        // filter tanggal di sini, misal:
                        // 'where' => 'PaymentType=="ACCRECPAYMENT"&&Date>=DateTime(2024,01,01)',
                        'where' => 'PaymentType=="ACCRECPAYMENT"',
                        'order' => 'Date ASC',
                        'page' => $page,
                    ]);

            if (!$response->successful() && $response->status() !== 429) {
                Log::error("[SyncXeroInvoiceJob] Fetch payment page $page gagal [{$response->status()}]: " . substr($response->body(), 0, 300));
            }

            return $response;

        } catch (\Exception $e) {
            Log::error("[SyncXeroInvoiceJob] Exception fetch payment page $page: " . $e->getMessage());
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