<?php

namespace App\Jobs;

use App\ConfigRefreshXero;
use App\Models\Expenses\Purchase\Bill\PBill;
// ADJUST: ganti namespace/nama class ini sesuai model Eloquent Anda untuk tabel d_bills.
// Saya asumsikan mengikuti pola penamaan PBill (p_bills) -> DBill (d_bills).
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

    // Xero hanya kembalikan 100 baris per halaman
    private const PER_PAGE = 100;

    // Berhenti & release job kalau sisa kuota per-menit sudah sekritis ini
    private const MIN_REM_THRESHOLD = 5;

    // Mulai memperlambat (proaktif) begitu sisa kuota per-menit di bawah ini,
    // supaya tidak nabrak ke MIN_REM_THRESHOLD / 429
    private const SLOWDOWN_THRESHOLD = 15;

    private const THROTTLE_PAGE_US = 400_000; // 400ms antar halaman bill
    private const THROTTLE_PAYMENT_US = 200_000; // 200ms antar payment fetch
    private const THROTTLE_SLOW_US = 1_000_000; // 1s extra saat sisa kuota menipis

    private array $tokenData;
    private string $jobId;

    /** @var GlobalService */
    protected $service_global;

    /**
     * Flag global: begitu true, SEMUA pemanggilan ke Xero (baik fetch bill
     * list maupun fetch payment) langsung dihentikan di titik manapun dia
     * sedang berjalan (loop bill, loop payment, dst), lalu job di-release.
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
            SyncJobStatus::where('job_id', $this->jobId)->update([
                'status' => 'running',
                'started_at' => now(),
            ]);

            $accessToken = $this->tokenData['access_token'];

            $tenantId = $this->getTenantId($accessToken);
            $page = 1;
            $totalSynced = 0;
            Log::info("[SyncBillJob][$this->jobId] Mulai sync bill (ACCPAY)...");
            do {
                $response = $this->fetchPage($accessToken, $tenantId, $page);

                if ($response === null) {
                    throw new \RuntimeException("fetchPage() mengembalikan null pada page $page (exception jaringan).");
                }

                // ── 429 Too Many Requests ───────────────────────────────────
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

                // ── Guard kuota + catat pemakaian via service_global ────────
                $this->guardRateLimit($response, "bill-list page $page");
                if ($this->shouldRelease) {
                    break;
                }

                // Xero tetap membungkus ACCPAY di key "Invoices" walau Type=ACCPAY.
                $bills = $response->json('Invoices') ?? [];

                foreach ($bills as $bill) {
                    // Cek flag SEBELUM proses bill berikutnya — kalau payment
                    // fetch bill sebelumnya sudah memicu release, jangan lanjut.
                    if ($this->shouldRelease) {
                        break;
                    }

                    $this->processBills($bill);
                    $totalSynced++;
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

            // ── Kalau ada sinyal release di titik manapun, requeue job ──────
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
    // RATE LIMIT GUARD (terpusat — dipakai bill list & payment fetch)
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

        Log::info("[SyncBillJob][$this->jobId] [$context] MinRem: $minRem | DayRem: $dayRem");

        if ($minRem <= self::MIN_REM_THRESHOLD) {
            Log::warning("[SyncBillJob][$this->jobId] Kuota kritis ($minRem/menit) di $context.");
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
    // STATUS MAPPER (Xero string -> kode numerik p_bills)
    // ================================================================

    /**
     * p_bills.status numerik: 0=draft, 1=awaiting, 2=paid.
     * Xero Status string: DRAFT, SUBMITTED, AUTHORISED, PAID, VOIDED, DELETED.
     *
     * ADJUST: silakan koreksi pemetaan AUTHORISED/VOIDED/DELETED kalau beda
     * dengan definisi bisnis "awaiting" di sistem Anda.
     */
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
    // PAYMENT SYNC
    // ================================================================

    /**
     * Sync satu payment — skip kalau sudah pernah tersimpan ATAU job sedang
     * dalam proses berhenti karena kuota kritis.
     *
     * Dedup dengan cek TransactionNominalBankAccount sebelum hit Xero —
     * tanpa ini, setiap sync ulang (cron harian) akan menarik ulang SEMUA
     * payment walau sudah ada di DB, salah satu penyebab terbesar kuota habis.
     */

    private function mapAmountsAre(?string $xeroLineAmountTypes): int
    {
        switch ($xeroLineAmountTypes) {
            case 'Inclusive':
                return 1;
            case 'NoTax':
                return 0;
            case 'Exclusive':
            default:
                // ADJUST: default Exclusive (2) dipakai kalau Xero tidak
                // mengirim LineAmountTypes sama sekali (jarang terjadi).
                return 2;
        }
    }
    public function getDetailPayment(string $idPayment, ?int $knownParentId = null): void
    {
        if ($this->shouldRelease) {
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

        $amount = (float) ($payment['Amount'] ?? 0);
        $accountCode = data_get($payment, 'Account.Code');
        $bankName = data_get($payment, 'Account.Name');
        $date = $this->parseXeroDate($payment['Date'] ?? null);
        $invoiceUuid = data_get($payment, 'Invoice.InvoiceID');
        $invoiceNumber = data_get($payment, 'Invoice.InvoiceNumber');
        $ref_payment = $payment['Reference'] ?? '-';


        // Pakai parent id yang sudah diketahui (dilempar dari processBills)
        // dulu kalau ada — hindari query tambahan ke PBill.
        $idParentInv = $knownParentId
            ?? ($invoiceUuid ? PBill::where('bills_uuid_xero', $invoiceUuid)->value('id') : null);

        $this->insertToDb($invoiceNumber, $bankName, $idPayment, $amount, $accountCode, $date, $ref_payment, $idParentInv);

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
            Log::warning("[SyncBillJob][insertToDb] AccountCode kosong. Payment {$paymentUuid} dilewati. Bill: $invNumber");
            return;
        }

        $findBank = BankXero::where('code', $accountCode)->first();

        if (!$findBank) {
            Log::warning(
                "[SyncBillJob][insertToDb] Kode akun bank tidak ditemukan: '{$accountCode}'. " .
                "Payment {$paymentUuid} dilewati. Nama bank: {$namaBank}. Bill: $invNumber"
            );
            return;
        }

        // updateOrCreate → idempoten, aman saat job di-retry/release/cron ulang
        TransactionNominalBankAccount::updateOrCreate(
            ['payment_uuid' => $paymentUuid],
            [
                'uuid_bank' => $findBank->id,
                // Bill (ACCPAY) = uang KELUAR, bukan masuk — kebalikan dari
                // sync Invoice (ACCREC). Sebelumnya kode ini salah taruh di
                // nominal_receive (warisan dari job invoice).
                'nominal_receive' => 0,
                'nominal_spend' => $amount,
                'created_by' => 1,
                'date_transaction' => $date,
                'nominal_transfer' => 0,
                'reference_detail' => $refDetail,
                'id_parent_bill' => $idParentInv,
            ]
        );
    }

    // ================================================================
    // BILL PROCESSING
    // ================================================================

    private function processBills(array $inv): void
    {
        $lineItems = $inv['LineItems'] ?? [];
        $issueDate = $this->parseXeroDate($inv['DateString'] ?? $inv['Date'] ?? null);
        $dueDate = $this->parseXeroDate($inv['DueDateString'] ?? $inv['DueDate'] ?? null);
        $contactId = data_get($inv, 'Contact.ContactID');

        // uuid_from = id lokal di tabel jamaah/kontak, BUKAN uuid Xero mentah
        // (p_bills tidak punya kolom uuid_contact/contact_name terpisah).
        $findContact = DataJamaahXero::where('uuid_contact', $contactId)->value('id') ?? 1;

        // ── 1. Upsert parent bill DULU ───────────────────────────────────
        // PENTING: ini harus jalan SEBELUM sync payment, supaya saat
        // getDetailPayment() mencari id_parent_invoice, baris bill-nya
        // sudah ada di DB.
        PBill::upsert(
            [
                [
                    'bills_uuid_xero' => $inv['InvoiceID'],
                    'uuid_from' => $findContact,
                    'date_req' => $issueDate,
                    'due_date' => $dueDate,
                    'reference' => $inv['InvoiceNumber'] ?? null,//$inv['InvoiceNumber'] ?? null,
                    // ADJUST: cek apakah "amounts_are" memang dimaksudkan
                    // menyimpan LineAmountTypes Xero (Exclusive/Inclusive/NoTax).
                    'amounts_are' => self::mapAmountsAre($inv['LineAmountTypes'] ?? null),
                    'subtotal' => $inv['SubTotal'] ?? 0,
                    'total' => $inv['Total'] ?? 0,
                    'tax' => $inv['TotalTax'] ?? 0,
                    'nominal_paid' => $inv['AmountPaid'] ?? 0,
                    'nominal_due' => $inv['AmountDue'] ?? 0,
                    'status' => self::mapBillStatus($inv['Status'] ?? null),
                    'currency' => $inv['CurrencyCode'] ?? null,
                    'created_by' => 1,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            ],
            ['bills_uuid_xero'],
            [
                'uuid_from',
                'date_req',
                'due_date',
                'reference',
                'amounts_are',
                'subtotal',
                'total',
                'tax',
                'nominal_paid',
                'nominal_due',
                'status',
                'currency',
                'updated_at',
            ]
        );

        $parentId = PBill::where('bills_uuid_xero', $inv['InvoiceID'])->value('id');

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

        // ── 3. Pre-load COA SEKALI sebelum loop — hindari N+1 ──────────────
        // (d_bills tidak punya kolom item_id, jadi tidak perlu lagi preload
        // ItemsPaketAllFromXero seperti pada job invoice).
        $accountCodes = collect($lineItems)->pluck('AccountCode')->filter()->unique()->values()->toArray();
        $coaMap = Coa::whereIn('code', $accountCodes)->pluck('id', 'code')->toArray();

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
            $itemCode = $line['ItemCode'] ?? data_get($line, 'Item.Code');

            // d_bills tidak punya kolom uuid Xero sendiri untuk line item, jadi
            // uuid_detail dipakai ganda: (a) key dedup upsert, (b) FK ke
            // transaction_all_coas. Pakai LineItemID Xero (selalu unik & stabil)
            // bukan random string, supaya upsert idempoten saat sync ulang.
            $uuidDetail = $line['LineItemID'] ?? $this->service_global->generateUniqueString();

            $batchDetails[] = [
                'bills_parent_id' => $parentId,
                'item_code' => $itemCode,
                'desc' => $line['Description'] ?? null,
                'qty' => $line['Quantity'] ?? 0,
                'unit_price' => $line['UnitAmount'] ?? 0,
                'account_id_coa' => $coaId,
                // ADJUST: ini saya isi dengan TaxAmount per baris (nominal),
                // bukan persentase. Kalau "tax_rate" memang harus berupa %,
                // hitung dari (TaxAmount / LineAmount * 100) atau dari TaxType.
                'tax_rate' => $line['TaxAmount'] ?? 0,
                'paket_tracking_uuid' => $paketUuid,
                'divisi_travel_tracking_uuid' => $divisiUuid,
                'amount' => $line['LineAmount'] ?? 0,
                'uuid_detail' => $uuidDetail,
                'updated_at' => now(),
                'created_at' => now(),
            ];
        }

        if (empty($batchDetails)) {
            return;
        }

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
                'updated_at',
            ]
        );

        // ── 5. Upsert TransactionAllCoa — hanya untuk bill AUTHORISED/PAID ──
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

                TransactionAllCoa::firstOrCreate(
                    ['uuid_detail' => $saved->uuid_detail],
                    [
                        'date_transaction' => $issueDate,
                        'uuid_coa' => $detail['account_id_coa'],
                        'reference' => $inv['Reference'] ?? '-',
                        // Bill = pengeluaran/expense -> is_speend = 1.
                        // ADJUST: cek konvensi flag ini di sistem Anda (0/1).
                        'is_speend' => 1,
                        'nominal' => $saved->amount,
                        'uuid_detail' => $saved->uuid_detail,
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
                        // Bills = Invoices dengan Type ACCPAY (Xero tidak punya
                        // endpoint /Bills terpisah). Sebelumnya ini ACCREC
                        // (Sales Invoice), itu sebabnya data bill tidak masuk.
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