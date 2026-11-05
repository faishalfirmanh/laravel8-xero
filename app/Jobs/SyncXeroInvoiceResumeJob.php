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
 * SyncXeroInvoiceResumeJob
 * ====================================================================
 * Ambil SEMUA invoice (ACCREC) + payment dari Xero, diurutkan berdasarkan
 * tanggal (Date ASC), dengan CURSOR TANGGAL yang disimpan di database
 * (tabel xero_sync_cursors).
 *
 * RESUME / INCREMENTAL
 *  - Setiap halaman selesai, cursor tanggal terakhir disimpan di DB.
 *  - Kuota Xero habis (429 / limit harian) -> job di-release sampai kuota
 *    reset. Job baru yang dibuat controller esok hari juga membaca cursor
 *    yang sama, jadi LANJUT dari data terakhir, bukan dari awal lagi.
 *  - Setelah selesai semua, cursor tetap tersimpan -> sync berikutnya
 *    hanya mengambil data dari tanggal terakhir (incremental).
 *  - Cursor per "scope":
 *      'ALL'        -> tanpa filter, dari data paling awal sampai terbaru
 *      'FROM:m-Y'   -> start_month: mulai tanggal 1 bulan tsb sampai TERBARU
 *                      (mis. 05-2026 -> 01-05-2026 s/d tanggal terbaru)
 *      'm-Y'        -> month_filter: hanya satu bulan itu saja
 *    start_month dan month_filter tidak dipakai bersamaan; kalau keduanya
 *    diisi, month_filter yang dipakai dan start_month diabaikan.
 *
 * INSERT-ONLY (tidak pernah replace / update / delete data lokal)
 *  - invoice_uuid sudah ada di lokal            -> dilewati total.
 *  - invoice_number bentrok dengan invoice lain -> nomor versi Xero
 *    di-generate ulang (INV-001 -> INV-001-X1), data lokal dibiarkan.
 *  - DataJamaahXero                             -> hanya dibaca.
 *  - ItemDetailInvoices                         -> insert line_item_uuid baru.
 *  - TransactionAllCoa                          -> firstOrCreate, tanpa delete.
 *  - TransactionNominalBankAccount              -> insert payment_uuid baru,
 *    hanya jika invoice induknya sudah ada di lokal.
 *
 * ShouldBeUnique per SCOPE (bukan per job_id): selama satu sync scope yang
 * sama masih "hidup" (termasuk menunggu release), dispatch baru dari
 * controller akan diabaikan supaya dua job tidak berebut kuota Xero.
 * ====================================================================
 */
class SyncXeroInvoiceResumeJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, ConfigRefreshXero;

    public int $timeout = 700;
    public int $uniqueFor = 26 * 3600;

    private const PER_PAGE = 100;
    private const PAYMENT_PER_PAGE = 100;

    // Mundur N hari dari cursor saat resume (data duplikat aman karena
    // invoice/payment yang sudah ada dilewati). Menangani urutan tak stabil
    // untuk tanggal yang sama di batas halaman.
    private const CURSOR_OVERLAP_DAYS = 1;

    // Lepas job & lanjut di run baru sebelum kena $timeout (cursor sudah tersimpan).
    private const RUN_BUDGET_SECS = 540;

    private const MIN_REM_THRESHOLD = 5;
    private const SLOWDOWN_THRESHOLD = 15;
    private const DAY_REM_CRITICAL = 50;
    private const THROTTLE_SLOW_US = 1_000_000;

    private const XERO_MIN_INTERVAL_US = 1_100_000; // ~54 request/menit
    private const XERO_PACING_CACHE_PREFIX = 'xero_api:last_request_at:';

    private const CURSOR_TABLE = 'xero_sync_cursors';

    private array $tokenData;
    private string $jobId;
    private ?string $monthFilter;
    private ?string $startMonth;
    private string $scope;

    /** @var GlobalService */
    protected $service_global;

    private bool $shouldRelease = false;
    private int $releaseAfterSecs = 0;
    private float $startedAt = 0.0;

    private array $trackingCache = [];
    private ?string $cachedTenantId = null;

    /**
     * @param string|null $monthFilter hanya satu bulan (format m-Y)
     * @param string|null $startMonth  mulai dari bulan ini sampai terbaru (format m-Y),
     *                                 mis. '05-2026' -> mulai 01-05-2026. Diabaikan
     *                                 kalau $monthFilter diisi.
     */
    public function __construct(
        array $tokenData,
        string $jobId,
        ?string $monthFilter = null,
        ?string $startMonth = null
    ) {
        $this->tokenData = $tokenData;
        $this->jobId = $jobId;
        $this->monthFilter = $monthFilter ?: null;
        $this->startMonth = $this->monthFilter ? null : ($startMonth ?: null);

        $this->scope = $this->monthFilter
            ?: ($this->startMonth ? 'FROM:' . $this->startMonth : 'ALL');

        $this->service_global = new GlobalService();
    }

    public function uniqueId(): string
    {
        return 'xero-invoice-resume:' . $this->scope;
    }

    public function retryUntil(): \DateTime
    {
        return now()->addHours(26);
    }

    public function backoff(): int
    {
        return 60;
    }

    // ================================================================
    // MAIN
    // ================================================================

    public function handle(): void
    {
        $this->startedAt = microtime(true);

        try {
            $accessToken = $this->tokenData['access_token'];
            $tenantId = $this->getTenantIdCached($accessToken);

            $jobStatus = SyncJobStatus::where('job_id', $this->jobId)->first();

            if (!$jobStatus) {
                $jobStatus = new SyncJobStatus();
                $jobStatus->job_id = $this->jobId;
                $jobStatus->job_type = 'SyncXeroInvoiceResumeJob';
                $jobStatus->total_synced = 0;
                $jobStatus->total_pages = 0;
                $jobStatus->total_payment_synced = 0;
                $jobStatus->total_pages_payment = 0;
                $jobStatus->current_phase = 'invoices';
                $jobStatus->save();
            }

            $cursor = $this->loadCursor();
            $phase = $cursor->phase;

            Log::info(
                "[SyncXeroResume][$this->jobId] Scope: {$this->scope} | phase: $phase | " .
                "cursor invoice: " . ($cursor->last_invoice_date ?? '-') .
                " | cursor payment: " . ($cursor->last_payment_date ?? '-')
            );

            if ($phase === 'invoices') {
                $this->syncInvoicesPhase($accessToken, $tenantId, $jobStatus, $cursor->last_invoice_date);

                if ($this->shouldRelease) {
                    $this->releaseNow('invoices');
                    return;
                }

                $this->updateCursor(['phase' => 'payments']);
                $jobStatus->current_phase = 'payments';
                $jobStatus->save();
                $phase = 'payments';
            }

            if ($phase === 'payments') {
                $cursor = $this->loadCursor();

                $this->syncPaymentsPhase($accessToken, $tenantId, $jobStatus, $cursor->last_payment_date);

                if ($this->shouldRelease) {
                    $this->releaseNow('payments');
                    return;
                }

                $this->updateCursor(['phase' => 'done']);
                $jobStatus->current_phase = 'done';
                $jobStatus->save();
            }

            Log::info(
                "[SyncXeroResume][$this->jobId] Selesai. " .
                "Invoice baru: {$jobStatus->total_synced} | Payment baru: {$jobStatus->total_payment_synced}"
            );
        } catch (\Exception $e) {
            Log::error("[SyncXeroResume][$this->jobId] Error: " . $e->getMessage());
            throw $e;
        }
    }

    private function releaseNow(string $phase): void
    {
        Log::warning(
            "[SyncXeroResume][$this->jobId] Release saat phase $phase. " .
            "Lanjut dari cursor setelah {$this->releaseAfterSecs}s."
        );
        $this->release($this->releaseAfterSecs);
    }

    // ================================================================
    // CURSOR (persisten di DB, lintas job & lintas hari)
    // ================================================================

    private function loadCursor(): object
    {
        $row = DB::table(self::CURSOR_TABLE)->where('scope', $this->scope)->first();

        if (!$row) {
            DB::table(self::CURSOR_TABLE)->insert([
                'scope' => $this->scope,
                'phase' => 'invoices',
                'last_invoice_date' => null,
                'last_payment_date' => null,
                'last_job_id' => $this->jobId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return DB::table(self::CURSOR_TABLE)->where('scope', $this->scope)->first();
        }

        // Siklus sebelumnya sudah selesai -> mulai siklus baru (incremental),
        // cursor tanggal TETAP dipakai.
        if ($row->phase === 'done') {
            $this->updateCursor(['phase' => 'invoices']);
            $row->phase = 'invoices';
        }

        return $row;
    }

    private function updateCursor(array $fields): void
    {
        DB::table(self::CURSOR_TABLE)->where('scope', $this->scope)->update(
            $fields + ['last_job_id' => $this->jobId, 'updated_at' => now()]
        );
    }

    /** Cursor hanya boleh maju, tidak pernah mundur. */
    private function advanceCursor(string $column, ?string $date): void
    {
        if (!$date) {
            return;
        }

        DB::table(self::CURSOR_TABLE)
            ->where('scope', $this->scope)
            ->where(function ($q) use ($column, $date) {
                $q->whereNull($column)->orWhere($column, '<', $date);
            })
            ->update([$column => $date, 'last_job_id' => $this->jobId, 'updated_at' => now()]);
    }

    /**
     * Tanggal awal request = cursor - overlap, dan tidak boleh lebih awal
     * dari awal bulan kalau month_filter dipakai.
     */
    private function resolveFromDate(?string $cursorDate): ?Carbon
    {
        $from = $cursorDate ? Carbon::parse($cursorDate)->subDays(self::CURSOR_OVERLAP_DAYS) : null;

        if ($this->monthFilter) {
            $monthStart = Carbon::createFromFormat('!m-Y', $this->monthFilter)->startOfMonth();
            if (!$from || $from->lt($monthStart)) {
                $from = $monthStart;
            }
        }

        // start_month: batas awal = tanggal 1 bulan tsb, TANPA batas atas.
        // Resume tetap pakai cursor kalau cursor sudah lebih maju dari batas ini.
        if ($this->startMonth) {
            $startFloor = Carbon::createFromFormat('!m-Y', $this->startMonth)->startOfMonth();
            if (!$from || $from->lt($startFloor)) {
                $from = $startFloor;
            }
        }

        return $from;
    }

    private function isOutOfTime(): bool
    {
        return (microtime(true) - $this->startedAt) >= self::RUN_BUDGET_SECS;
    }

    // ================================================================
    // PHASE 1 — INVOICES
    // ================================================================

    private function syncInvoicesPhase(string $accessToken, string $tenantId, SyncJobStatus $jobStatus, ?string $cursorDate): void
    {
        $fromDate = $this->resolveFromDate($cursorDate);
        $page = 1;
        $totalImported = (int) ($jobStatus->total_synced ?? 0);

        if ($fromDate) {
            Log::info("[SyncXeroResume][$this->jobId] Invoice dilanjutkan dari tanggal {$fromDate->toDateString()}.");
        } else {
            Log::info("[SyncXeroResume][$this->jobId] Invoice dimulai dari awal (belum ada cursor).");
        }

        do {
            $response = $this->fetchInvoicesPage($accessToken, $tenantId, $page, $fromDate);

            if ($response === null) {
                throw new \RuntimeException("fetchInvoicesPage() null pada page $page (exception jaringan).");
            }

            if ($response->status() === 429) {
                $retryAfter = (int) ($response->header('Retry-After') ?? 60);
                Log::warning("[SyncXeroResume][$this->jobId] 429 di invoice page $page. Re-queue {$retryAfter}s.");
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

            // invoice yang sudah ada di lokal -> tidak disentuh
            $existing = [];
            if (!empty($invoices)) {
                $existing = InvoicesAllFromXero::whereIn('invoice_uuid', array_column($invoices, 'InvoiceID'))
                    ->pluck('invoice_uuid')->flip()->toArray();
            }

            $imported = 0;
            foreach ($invoices as $inv) {
                if (isset($existing[$inv['InvoiceID']])) {
                    continue;
                }

                try {
                    $this->processInvoice($inv);
                    $imported++;
                } catch (\Exception $e) {
                    Log::error(
                        "[SyncXeroResume][$this->jobId] Error invoice " .
                        ($inv['InvoiceNumber'] ?? $inv['InvoiceID']) . ": " . $e->getMessage()
                    );
                }
            }

            $totalImported += $imported;

            // Cursor maju SETELAH halaman diproses
            $this->advanceCursor('last_invoice_date', $this->maxDate($invoices));

            $jobStatus->total_synced = $totalImported;
            $jobStatus->total_pages = $page;
            $jobStatus->save();

            Log::info(
                "[SyncXeroResume][$this->jobId] Invoice page $page: " . count($invoices) .
                " dari Xero | baru $imported | sudah ada " . count($existing) . " | total baru run ini: $totalImported"
            );

            $hasNextPage = count($invoices) === self::PER_PAGE;
            $page++;

            if ($hasNextPage && $this->isOutOfTime()) {
                $this->triggerRelease(5);
                break;
            }
        } while ($hasNextPage);

        if (!$this->shouldRelease) {
            Log::info("[SyncXeroResume][$this->jobId] Phase invoices selesai. Invoice baru: $totalImported.");
        }
    }

    private function processInvoice(array $inv): void
    {
        DB::transaction(function () use ($inv) {
            $lineItems = $inv['LineItems'] ?? [];
            $firstLine = $lineItems[0] ?? [];
            $issueDate = $this->parseXeroDate($inv['DateString'] ?? $inv['Date'] ?? null);
            $dueDate = $this->parseXeroDate($inv['DueDateString'] ?? $inv['DueDate'] ?? null);
            $contactId = data_get($inv, 'Contact.ContactID');
            $currencyCode = $inv['CurrencyCode'] ?? 'IDR';
            $nominalCurr = $currencyCode === 'SAR' ? $this->getCurrentRate($inv) : 1;

            // DataJamaahXero: hanya dibaca
            $findContact = DataJamaahXero::where('uuid_contact', $contactId)->value('id') ?? 1;

            $invoiceNumber = $this->resolveInvoiceNumber($inv['InvoiceNumber'] ?? null);

            // INSERT saja -> tidak pernah menimpa
            $parentId = InvoicesAllFromXero::query()->insertGetId([
                'invoice_uuid' => $inv['InvoiceID'],
                'invoice_number' => $invoiceNumber,
                'invoice_amount' => $inv['AmountPaid'] ?? 0,
                'invoice_total' => $inv['Total'] ?? 0,
                'less_nominal' => $inv['AmountDue'] ?? 0,
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'status' => $inv['Status'] ?? null,
                'uuid_contact' => $contactId,
                'contact_name' => data_get($inv, 'Contact.Name'),
                'contact_id' => $findContact,
                'uuid_proudct_and_service' => $firstLine['ItemID'] ?? data_get($firstLine, 'Item.ItemID'),
                'item_name' => $firstLine['Description'] ?? null,
                'reference' => $inv['Reference'] ?? null,
                'updated_at' => now(),
                'created_at' => now(),
                'code_curr' => $currencyCode,
                'nominal_currency' => $nominalCurr,
            ]);

            if (empty($lineItems)) {
                return;
            }

            $accountCodes = collect($lineItems)->pluck('AccountCode')->filter()->unique()->values()->toArray();
            $itemCodes = collect($lineItems)->map(fn($l) => $this->lineItemCode($l))
                ->filter()->unique()->values()->toArray();

            $coaMap = Coa::whereIn('code', $accountCodes)->pluck('id', 'code')->toArray();
            $itemMap = ItemsPaketAllFromXero::whereIn('code', $itemCodes)->pluck('id', 'code')->toArray();

            // line_item_uuid yang sudah ada di lokal -> tidak disentuh
            $incomingLineIds = collect($lineItems)->pluck('LineItemID')->filter()->values()->toArray();
            $existingLineIds = ItemDetailInvoices::whereIn('line_item_uuid', $incomingLineIds)
                ->pluck('line_item_uuid')->flip()->toArray();

            $batchDetails = [];

            foreach ($lineItems as $line) {
                if (empty($line['LineItemID'])) {
                    Log::warning('[SyncXeroResume] LineItemID kosong, skip', ['invoice' => $inv['InvoiceID']]);
                    continue;
                }

                if (isset($existingLineIds[$line['LineItemID']])) {
                    continue;
                }

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

                $itemCode = $this->lineItemCode($line);

                $batchDetails[] = [
                    'invoice_number' => $invoiceNumber,
                    'uuid_invoices' => $inv['InvoiceID'],
                    'uuid_item' => $line['Item']['ItemID'] ?? $line['ItemID'] ?? 'no_set',
                    'qty' => $line['Quantity'] ?? 0,
                    'unit_price' => $line['UnitAmount'] ?? 0,
                    'total_amount_each_row' => $line['LineAmount'] ?? 0,
                    'line_item_uuid' => $line['LineItemID'],
                    'coa_id' => isset($line['AccountCode']) ? ($coaMap[$line['AccountCode']] ?? null) : null,
                    'parent_inv_id' => $parentId,
                    'item_id' => $itemCode ? ($itemMap[$itemCode] ?? null) : null,
                    'uuid_detail_inv' => $line['LineItemID'],
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

            ItemDetailInvoices::insert($batchDetails);

            // Jurnal COA hanya untuk AUTHORISED / PAID. TIDAK ada delete.
            $status = $inv['Status'] ?? null;
            if ($status !== 'AUTHORISED' && $status !== 'PAID') {
                return;
            }

            $savedDetails = ItemDetailInvoices::whereIn('line_item_uuid', array_column($batchDetails, 'line_item_uuid'))
                ->get()->keyBy('line_item_uuid');

            foreach ($batchDetails as $detail) {
                if (empty($detail['coa_id'])) {
                    continue;
                }

                $saved = $savedDetails[$detail['line_item_uuid']] ?? null;
                if (!$saved) {
                    continue;
                }

                // firstOrCreate: kalau sudah ada, TIDAK diubah
                TransactionAllCoa::firstOrCreate(
                    ['uuid_detail' => $saved->uuid_detail_inv],
                    [
                        'date_transaction' => $issueDate,
                        'uuid_coa' => $detail['coa_id'],
                        'reference' => $inv['Reference'] ?? '-',
                        'is_speend' => 0,
                        'nominal' => $saved->total_amount_each_row,
                        'uuid_detail' => $saved->uuid_detail_inv,
                        'code_curr' => $currencyCode,
                        'nominal_currency' => $nominalCurr,
                        'base_nominal' => $currencyCode === 'SAR'
                            ? (float) $nominalCurr * $saved->total_amount_each_row
                            : $saved->total_amount_each_row,
                    ]
                );
            }
        }, 5);
    }

    /**
     * Nomor invoice Xero bentrok dengan invoice lokal lain -> generate ulang
     * (suffix -X1, -X2, ...). Data lokal tidak diubah.
     */
    private function resolveInvoiceNumber(?string $number): ?string
    {
        if ($number === null || $number === '') {
            return $number;
        }

        if (!InvoicesAllFromXero::where('invoice_number', $number)->exists()) {
            return $number;
        }

        $i = 1;
        do {
            $candidate = "{$number}-X{$i}";
            $i++;
        } while (InvoicesAllFromXero::where('invoice_number', $candidate)->exists());

        Log::warning("[SyncXeroResume][$this->jobId] Nomor invoice '$number' sudah ada di lokal -> dipakai '$candidate'.");

        return $candidate;
    }

    // ================================================================
    // PHASE 2 — PAYMENTS (bulk, insert-only)
    // ================================================================

    private function syncPaymentsPhase(string $accessToken, string $tenantId, SyncJobStatus $jobStatus, ?string $cursorDate): void
    {
        $fromDate = $this->resolveFromDate($cursorDate);
        $page = 1;
        $totalInserted = (int) ($jobStatus->total_payment_synced ?? 0);

        if ($fromDate) {
            Log::info("[SyncXeroResume][$this->jobId] Payment dilanjutkan dari tanggal {$fromDate->toDateString()}.");
        }

        do {
            $response = $this->fetchPaymentsPage($accessToken, $tenantId, $page, $fromDate);

            if ($response === null) {
                throw new \RuntimeException("fetchPaymentsPage() null pada page $page (exception jaringan).");
            }

            if ($response->status() === 429) {
                $retryAfter = (int) ($response->header('Retry-After') ?? 60);
                Log::warning("[SyncXeroResume][$this->jobId] 429 di payment page $page. Re-queue {$retryAfter}s.");
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
            $inserted = $this->processPaymentsBatch($payments);
            $totalInserted += $inserted;

            $this->advanceCursor('last_payment_date', $this->maxDate($payments));

            $jobStatus->total_payment_synced = $totalInserted;
            $jobStatus->total_pages_payment = $page;
            $jobStatus->save();

            Log::info(
                "[SyncXeroResume][$this->jobId] Payment page $page: " . count($payments) .
                " dari Xero | baru $inserted | total baru run ini: $totalInserted"
            );

            $hasNextPage = count($payments) === self::PAYMENT_PER_PAGE;
            $page++;

            if ($hasNextPage && $this->isOutOfTime()) {
                $this->triggerRelease(5);
                break;
            }
        } while ($hasNextPage);

        if (!$this->shouldRelease) {
            Log::info("[SyncXeroResume][$this->jobId] Phase payments selesai. Payment baru: $totalInserted.");
        }
    }

    /**
     * @return int jumlah payment baru yang diinsert
     */
    private function processPaymentsBatch(array $payments): int
    {
        $payments = array_values(array_filter(
            $payments,
            fn($p) => !empty($p['PaymentID']) && ($p['Status'] ?? '') !== 'DELETED'
        ));

        if (empty($payments)) {
            return 0;
        }

        // payment_uuid yang sudah ada di lokal -> tidak disentuh
        $existing = TransactionNominalBankAccount::whereIn('payment_uuid', array_column($payments, 'PaymentID'))
            ->pluck('payment_uuid')->flip()->toArray();

        $payments = array_values(array_filter($payments, fn($p) => !isset($existing[$p['PaymentID']])));

        if (empty($payments)) {
            return 0;
        }

        $invoiceUuids = collect($payments)->map(fn($p) => data_get($p, 'Invoice.InvoiceID'))
            ->filter()->unique()->values()->toArray();
        $parentMap = InvoicesAllFromXero::whereIn('invoice_uuid', $invoiceUuids)
            ->pluck('id', 'invoice_uuid')->toArray();

        $accountCodes = collect($payments)->map(fn($p) => data_get($p, 'Account.Code'))
            ->filter()->unique()->values()->toArray();
        $bankMap = BankXero::whereIn('code', $accountCodes)->pluck('id', 'code')->toArray();

        $rows = [];

        foreach ($payments as $p) {
            $paymentId = $p['PaymentID'];
            $invoiceUuid = data_get($p, 'Invoice.InvoiceID');

            // Invoice induk belum ada di lokal -> payment dilewati
            if (!$invoiceUuid || !isset($parentMap[$invoiceUuid])) {
                continue;
            }

            $accountCode = data_get($p, 'Account.Code');
            if (!$accountCode || !isset($bankMap[$accountCode])) {
                Log::warning(
                    "[SyncXeroResume] Kode akun bank tidak ditemukan/kosong: '" . ($accountCode ?? '-') .
                    "'. Payment {$paymentId} dilewati."
                );
                continue;
            }

            $invoiceCurr = strtoupper(data_get($p, 'Invoice.CurrencyCode') ?? 'IDR');
            $currencyRate = (float) ($p['CurrencyRate'] ?? 0);
            $amount = (float) ($p['Amount'] ?? 0);

            $nominalCurr = 1;
            $totalBaseRecv = $amount;

            if ($invoiceCurr === 'SAR' && $currencyRate > 0) {
                $nominalCurr = number_format(1 / $currencyRate, 2, '.', '');
                $totalBaseRecv = round($amount / $currencyRate, 0);
            }

            $rows[] = [
                'payment_uuid' => $paymentId,
                'uuid_bank' => $bankMap[$accountCode],
                'nominal_receive' => $amount,
                'created_by' => 1,
                'date_transaction' => $this->parseXeroDate($p['DateString'] ?? $p['Date'] ?? null),
                'nominal_spend' => 0,
                'nominal_transfer' => 0,
                'reference_detail' => data_get($p, 'Reference'),
                'id_parent_invoice' => $parentMap[$invoiceUuid],
                'updated_at' => now(),
                'created_at' => now(),
                'nominal_currency' => $nominalCurr,
                'total_base_receive' => $totalBaseRecv,
            ];
        }

        if (empty($rows)) {
            return 0;
        }

        TransactionNominalBankAccount::insert($rows);

        return count($rows);
    }

    // ================================================================
    // RATE LIMITER
    // ================================================================

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

    private function getTenantIdCached(string $accessToken): string
    {
        if ($this->cachedTenantId === null) {
            $this->cachedTenantId = $this->getTenantId($accessToken);
        }

        return $this->cachedTenantId;
    }

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

        Log::info("[SyncXeroResume][$this->jobId] [$context] MinRem: $minRem | DayRem: $dayRem");

        // Kuota harian hampir habis -> release sampai reset (~tengah malam UTC).
        // Cursor sudah tersimpan, jadi lanjut dari data terakhir.
        if ($dayRemHeader !== null && $dayRemHeader !== '' && $dayRem <= self::DAY_REM_CRITICAL) {
            $secondsUntilResetUtc = (int) now('UTC')->endOfDay()->diffInSeconds(now('UTC')) + 120;
            Log::warning("[SyncXeroResume][$this->jobId] Kuota HARIAN kritis ($dayRem) di $context. Release ~{$secondsUntilResetUtc}s.");
            $this->triggerRelease($secondsUntilResetUtc);
            return;
        }

        if ($minRem <= self::MIN_REM_THRESHOLD) {
            Log::warning("[SyncXeroResume][$this->jobId] Kuota kritis ($minRem/menit) di $context.");
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
    // XERO API
    // ================================================================

    private function fetchInvoicesPage(string $accessToken, string $tenantId, int $page, ?Carbon $fromDate): ?Response
    {
        $this->waitForXeroSlot($tenantId);

        $where = 'Type=="ACCREC"';

        if ($fromDate) {
            $where .= sprintf('&&Date>=DateTime(%d,%d,%d)', $fromDate->year, $fromDate->month, $fromDate->day);
        }

        if ($this->monthFilter) {
            $end = Carbon::createFromFormat('!m-Y', $this->monthFilter)->endOfMonth();
            $where .= sprintf('&&Date<=DateTime(%d,%d,%d)', $end->year, $end->month, $end->day);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Xero-Tenant-Id' => $tenantId,
                'Accept' => 'application/json',
            ])->timeout(25)->get('https://api.xero.com/api.xro/2.0/Invoices', [
                        'Statuses' => 'DRAFT,SUBMITTED,AUTHORISED,PAID',
                        'where' => $where,
                        'order' => 'Date ASC',
                        'page' => $page,
                        'unitdp' => 4,
                        'pageSize' => self::PER_PAGE,
                    ]);

            if (!$response->successful() && $response->status() !== 429) {
                Log::error("[SyncXeroResume] Fetch invoice page $page gagal [{$response->status()}]: " . substr($response->body(), 0, 300));
            }

            return $response;
        } catch (\Exception $e) {
            Log::error("[SyncXeroResume] Exception fetch invoice page $page: " . $e->getMessage());
            return null;
        }
    }

    private function fetchPaymentsPage(string $accessToken, string $tenantId, int $page, ?Carbon $fromDate): ?Response
    {
        $this->waitForXeroSlot($tenantId);

        // Payment tidak dibatasi batas atas bulan: pembayaran bisa jatuh
        // di bulan setelah invoice.
        $where = 'PaymentType=="ACCRECPAYMENT"';

        if ($fromDate) {
            $where .= sprintf('&&Date>=DateTime(%d,%d,%d)', $fromDate->year, $fromDate->month, $fromDate->day);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Xero-Tenant-Id' => $tenantId,
                'Accept' => 'application/json',
            ])->timeout(25)->get('https://api.xero.com/api.xro/2.0/Payments', [
                        'where' => $where,
                        'order' => 'Date ASC',
                        'page' => $page,
                    ]);

            if (!$response->successful() && $response->status() !== 429) {
                Log::error("[SyncXeroResume] Fetch payment page $page gagal [{$response->status()}]: " . substr($response->body(), 0, 300));
            }

            return $response;
        } catch (\Exception $e) {
            Log::error("[SyncXeroResume] Exception fetch payment page $page: " . $e->getMessage());
            return null;
        }
    }

    // ================================================================
    // HELPERS
    // ================================================================

    /** Tanggal terbesar (Y-m-d) dari daftar invoice/payment Xero. */
    private function maxDate(array $rows): ?string
    {
        $max = null;

        foreach ($rows as $r) {
            $d = $this->parseXeroDate($r['DateString'] ?? $r['Date'] ?? null);
            if ($d && ($max === null || $d > $max)) {
                $max = $d;
            }
        }

        return $max;
    }

    private function lineItemCode(array $line): ?string
    {
        $code = $line['Item']['Code'] ?? $line['ItemCode'] ?? null;
        return $code !== null ? trim((string) $code) : null;
    }

    public function getCurrentRate($inv)
    {
        $invoiceRate = (float) ($inv['CurrencyRate'] ?? 0);

        if ($invoiceRate <= 0) {
            return 1;
        }

        return number_format(1 / $invoiceRate, 2, '.', '');
    }

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

    private function parseXeroDate(?string $dateStr): ?string
    {
        if (!$dateStr) {
            return null;
        }

        // Format lama Xero: /Date(1573755038314+0000)/ (cek lebih dulu,
        // karena offset negatif mengandung '-')
        if (preg_match('/\/Date\((-?\d+)/', $dateStr, $matches)) {
            return Carbon::createFromTimestampMs((int) $matches[1], 'UTC')->format('Y-m-d');
        }

        if (strpos($dateStr, 'T') !== false || strpos($dateStr, '-') !== false) {
            try {
                return Carbon::parse($dateStr)->format('Y-m-d');
            } catch (\Exception $e) {
                Log::warning("[parseXeroDate] Gagal parse tanggal ISO: $dateStr");
                return null;
            }
        }

        Log::warning("[parseXeroDate] Format tanggal tidak dikenali: $dateStr");
        return null;
    }
}