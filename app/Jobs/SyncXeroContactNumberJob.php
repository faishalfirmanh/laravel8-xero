<?php

namespace App\Jobs;

use App\ConfigRefreshXero;
use App\Models\DataJamaah;
use App\Models\SyncJobStatus;
use App\Services\GlobalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncXeroContactNumberJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, ConfigRefreshXero;

    public int $timeout = 700;

    private const PER_PAGE = 100;
    private const MIN_REM_THRESHOLD = 5;
    private const SLOWDOWN_THRESHOLD = 15;
    private const THROTTLE_PAGE_US = 400_000;   // 400ms antar halaman
    private const THROTTLE_UPDATE_US = 300_000;   // 300ms antar batch POST
    private const BATCH_UPDATE_SIZE = 50;        // max 50 kontak per POST

    private array $tokenData;
    private string $jobId;

    /** @var GlobalService */
    protected $service_global;

    private bool $shouldRelease = false;
    private int $releaseAfterSecs = 60;

    private ?string $tenantId = null;

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
    // MAIN
    // ================================================================

    public function handle(): void
    {
        try {
            SyncJobStatus::where('job_id', $this->jobId)->update([
                'status' => 'running',
                'started_at' => now(),
            ]);

            $accessToken = $this->tokenData['access_token'] ?? null;
            if (!$accessToken) {
                throw new \RuntimeException("Access token kosong.");
            }

            $this->tenantId = $this->getTenantId($accessToken);
            $tenantId = $this->tenantId;

            if (!$tenantId) {
                throw new \RuntimeException("Tenant ID Xero tidak tersedia.");
            }

            // Resume dari halaman terakhir kalau job pernah di-release
            $page = (int) (SyncJobStatus::where('job_id', $this->jobId)
                ->value('last_page') ?? 0) + 1;

            $totalScanned = 0;
            $totalUpdated = 0;
            $totalSkipped = 0;

            Log::info("[SyncXeroContactNumberJob][{$this->jobId}] Mulai dari page {$page}");

            do {
                $response = $this->fetchNullContactPage($accessToken, $tenantId, $page);

                if ($response === null) {
                    throw new \RuntimeException("fetchNullContactPage() null di page {$page}.");
                }

                // ── 429 ─────────────────────────────────────────────────
                if ($response->status() === 429) {
                    $retryAfter = (int) ($response->header('Retry-After') ?? 60);
                    Log::warning("[SyncXeroContactNumberJob][{$this->jobId}] 429 di page {$page}. Release {$retryAfter}s.");
                    $this->triggerRelease($retryAfter);
                    break;
                }

                if (!$response->successful()) {
                    throw new \RuntimeException(
                        "Fetch page {$page} gagal. HTTP {$response->status()}: " . substr($response->body(), 0, 300)
                    );
                }

                $this->guardRateLimit($response, "contacts page {$page}");
                if ($this->shouldRelease)
                    break;

                $contacts = $response->json('Contacts') ?? [];

                // Filter di sisi PHP: pastikan ContactNumber benar-benar null/kosong
                $eligible = [];
                foreach ($contacts as $c) {
                    $num = $c['ContactNumber'] ?? null;
                    if ($num === null || trim((string) $num) === '') {
                        if (!empty($c['ContactID']) && !empty($c['Name'])) {
                            $eligible[] = $c;
                        }
                    }
                }

                $totalScanned += count($eligible);

                if (!empty($eligible)) {
                    [$updated, $skipped] = $this->matchAndUpdate($eligible, $accessToken, $tenantId);
                    $totalUpdated += $updated;
                    $totalSkipped += $skipped;
                }

                SyncJobStatus::where('job_id', $this->jobId)->update([
                    'total_synced' => $totalUpdated,
                    'total_pages' => $page,
                    'last_page' => $page,
                ]);

                Log::info("[SyncXeroContactNumberJob][{$this->jobId}] Page {$page} selesai. " .
                    "Scanned={$totalScanned}, Updated={$totalUpdated}, Skipped={$totalSkipped}");

                if ($this->shouldRelease)
                    break;

                $hasNextPage = count($contacts) === self::PER_PAGE;
                $page++;

                if ($hasNextPage) {
                    usleep(self::THROTTLE_PAGE_US);
                }

            } while ($hasNextPage);

            // ── Requeue kalau kuota kritis ─────────────────────────────
            if ($this->shouldRelease) {
                Log::warning("[SyncXeroContactNumberJob][{$this->jobId}] Kuota kritis. " .
                    "Release {$this->releaseAfterSecs}s. Progress: Updated={$totalUpdated}");
                $this->release($this->releaseAfterSecs);
                return;
            }

            SyncJobStatus::where('job_id', $this->jobId)->update([
                'status' => 'success',
                'finished_at' => now(),
            ]);

            Log::info("[SyncXeroContactNumberJob][{$this->jobId}] Selesai. " .
                "Scanned={$totalScanned}, Updated={$totalUpdated}, Skipped={$totalSkipped}");

        } catch (\Throwable $e) {
            SyncJobStatus::where('job_id', $this->jobId)->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            Log::error("[SyncXeroContactNumberJob][{$this->jobId}] Error: " . $e->getMessage());
            throw $e;
        }
    }

    // ================================================================
    // MATCH & UPDATE
    // ================================================================

    /**
     * Cocokkan Name kontak Xero dengan nama_jamaah DataJamaah (case-insensitive),
     * lalu update ContactNumber via POST /Contacts.
     *
     * @return array{0:int,1:int} [updatedCount, skippedCount]
     */
    private function matchAndUpdate(array $contacts, string $accessToken, string $tenantId): array
    {
        // Kumpulkan nama unik (lowercase + trim)
        $nameKeys = [];
        foreach ($contacts as $c) {
            $k = mb_strtolower(trim((string) $c['Name']));
            if ($k !== '')
                $nameKeys[$k] = true;
        }
        $nameKeys = array_keys($nameKeys);

        if (empty($nameKeys))
            return [0, count($contacts)];

        // Ambil DataJamaah yang match. Pakai LOWER(TRIM()) supaya fleksibel.
        $jamaahMap = DataJamaah::whereNotNull('no_ktp')
            ->where('no_ktp', '!=', '')
            ->whereIn(DB::raw('LOWER(TRIM(nama_jamaah))'), $nameKeys)
            ->get(['nama_jamaah', 'no_ktp'])
            ->mapWithKeys(function ($j) {
                $k = mb_strtolower(trim($j->nama_jamaah));
                return [$k => trim((string) $j->no_ktp)];
            })
            ->toArray();

        if (empty($jamaahMap)) {
            Log::info("[SyncXeroContactNumberJob][{$this->jobId}] Tidak ada nama Xero yang match di DataJamaah.");
            return [0, count($contacts)];
        }

        // Bangun payload update + deteksi duplikat nama dalam 1 batch
        $updates = [];
        $skipped = 0;
        $nameSeen = [];

        foreach ($contacts as $c) {
            $k = mb_strtolower(trim((string) $c['Name']));

            if (!isset($jamaahMap[$k])) {
                $skipped++;
                continue;
            }

            // Kalau ada 2 kontak Xero dengan Name sama, kita tetap update keduanya
            // (pakai ContactID masing-masing) — biar aman, tapi log warning.
            if (isset($nameSeen[$k])) {
                Log::warning("[SyncXeroContactNumberJob][{$this->jobId}] Duplikat Name di Xero: '{$c['Name']}' " .
                    "({$nameSeen[$k]} vs {$c['ContactID']}) — keduanya akan di-update.");
            }
            $nameSeen[$k] = $c['ContactID'];

            $updates[] = [
                'ContactID' => $c['ContactID'],
                'Name' => $c['Name'],
                // 'ContactNumber' => $jamaahMap[$k],
                'AccountNumber' => $jamaahMap[$k],
            ];
        }

        if (empty($updates))
            return [0, $skipped];

        Log::info("[SyncXeroContactNumberJob][{$this->jobId}] Akan update " . count($updates) . " kontak.");

        // ── Kirim batch ────────────────────────────────────────────────
        $successCount = 0;
        foreach (array_chunk($updates, self::BATCH_UPDATE_SIZE) as $chunk) {
            if ($this->shouldRelease)
                break;

            $resp = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Xero-Tenant-Id' => $tenantId,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->timeout(30)->post(
                    'https://api.xero.com/api.xro/2.0/Contacts',
                    ['Contacts' => $chunk]
                );

            if ($resp->status() === 429) {
                $retryAfter = (int) ($resp->header('Retry-After') ?? 60);
                Log::warning("[SyncXeroContactNumberJob][{$this->jobId}] 429 saat update. Release {$retryAfter}s.");
                $this->triggerRelease($retryAfter);
                break;
            }

            if (!$resp->successful()) {
                Log::error("[SyncXeroContactNumberJob][{$this->jobId}] Update gagal HTTP {$resp->status()}: "
                    . substr($resp->body(), 0, 500));
                continue; // lanjut chunk berikutnya, jangan matikan job
            }

            $this->guardRateLimit($resp, "contacts update");

            // Cek ValidationErrors per kontak
            $updated = $resp->json('Contacts') ?? [];
            foreach ($updated as $idx => $u) {
                $errs = $u['ValidationErrors'] ?? [];
                if (empty($errs)) {
                    $successCount++;
                    Log::info("[SyncXeroContactNumberJob][{$this->jobId}] ✓ " .
                        ($u['Name'] ?? '?') . " → ContactNumber=" . ($u['ContactNumber'] ?? '-'));
                } else {
                    Log::warning("[SyncXeroContactNumberJob][{$this->jobId}] ✗ " .
                        ($u['Name'] ?? '?') . " validation error: " . json_encode($errs));
                }
            }

            usleep(self::THROTTLE_UPDATE_US);
        }

        return [$successCount, $skipped];
    }

    // ================================================================
    // RATE LIMIT GUARD
    // ================================================================

    private function guardRateLimit(Response $response, string $context): void
    {
        $minRemHeader = $response->header('X-MinLimit-Remaining');
        $dayRemHeader = $response->header('X-DayLimit-Remaining');

        if ($minRemHeader === null || $minRemHeader === '')
            return;

        $minRem = (int) $minRemHeader;
        $dayRem = (int) ($dayRemHeader ?? 0);

        $this->service_global->requestCalculationXero($minRem, $dayRem);

        Log::info("[SyncXeroContactNumberJob][{$this->jobId}] [{$context}] MinRem={$minRem} DayRem={$dayRem}");

        if ($minRem <= self::MIN_REM_THRESHOLD) {
            Log::warning("[SyncXeroContactNumberJob][{$this->jobId}] Kuota kritis ({$minRem}/menit).");
            $this->triggerRelease(65);
            return;
        }

        if ($minRem <= self::SLOWDOWN_THRESHOLD) {
            usleep(1_000_000);
        }
    }

    private function triggerRelease(int $seconds): void
    {
        $this->shouldRelease = true;
        $this->releaseAfterSecs = max($this->releaseAfterSecs, $seconds);
    }

    // ================================================================
    // FETCH
    // ================================================================

    private function fetchNullContactPage(string $accessToken, string $tenantId, int $page): ?Response
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Xero-Tenant-Id' => $tenantId,
                'Accept' => 'application/json',
            ])->timeout(30)->get(
                    'https://api.xero.com/api.xro/2.0/Contacts',
                    [
                        'where' => 'AccountNumber == null',
                        'summaryOnly' => 'true',
                        'page' => $page,
                    ]
                );

            if (!$response->successful() && $response->status() !== 429) {
                Log::error("[SyncXeroContactNumberJob] Fetch page {$page} gagal [{$response->status()}]: "
                    . substr($response->body(), 0, 300));
            }

            return $response;

        } catch (\Throwable $e) {
            Log::error("[SyncXeroContactNumberJob] Exception fetch page {$page}: " . $e->getMessage());
            return null;
        }
    }
}