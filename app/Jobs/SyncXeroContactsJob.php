<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Http\Repository\MasterData\JamaahXeroRepository;

class SyncXeroContactsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // ✅ FIX 1: Hapus typed property, pakai tanpa type hint
    // Typed property butuh PHP 7.4 verified — ini lebih aman
    public $timeout = 1800;
    public $tries = 3;

    // ✅ FIX 2: Ganti dari $tokenData/$jobId → $accessToken/$tenantId
    // agar fetchPage() bisa langsung pakai $this->accessToken
    private $accessToken;
    private $tenantId;
    private $xeroBaseUrl = 'https://api.xero.com/api.xro/2.0';

    public const CACHE_KEY = 'xero_contact_sync_status';

    // ✅ FIX 3: Constructor sesuai dengan dispatch() di controller
    // Controller: SyncXeroContactsJob::dispatch($accessToken, $tenantId)
    public function __construct(string $accessToken, string $tenantId)
    {
        $this->accessToken = $accessToken;
        $this->tenantId = $tenantId;
    }

    public function handle(JamaahXeroRepository $repo): void
    {
        $page = 1;
        $totalFetched = 0;
        $totalSaved = 0;
        $retryCount = 0;
        $maxRetry = 3;
        $startedAt = now()->toDateTimeString();

        $this->updateCache('running', $page, $totalFetched, $totalSaved, $startedAt);

        while (true) {

            Log::info("[Xero Sync] Fetching page {$page}...");

            $contacts = $this->fetchPage($page);

            if ($contacts === null) {
                if ($retryCount < $maxRetry) {
                    $retryCount++;
                    Log::warning("[Xero Sync] Rate limit/error. Retry {$retryCount}/{$maxRetry} — tunggu 65 detik...");
                    sleep(65);
                    continue;
                }

                Log::error("[Xero Sync] Gagal setelah {$maxRetry} retry di page {$page}. Berhenti.");
                Cache::put(self::CACHE_KEY, [
                    'status' => 'failed',
                    'failed_at_page' => $page,
                    'total_fetched' => $totalFetched,
                    'total_saved' => $totalSaved,
                    'failed_at' => now()->toDateTimeString(),
                ], now()->addHours(2));
                return;
            }

            $retryCount = 0;

            if (empty($contacts)) {
                Log::info("[Xero Sync] Page {$page} kosong. Selesai.");
                break;
            }

            $clean = $this->mapContacts($contacts);
            $totalFetched += count($clean);

            $batch = array_map(function ($acc) {
                return [
                    'uuid_contact' => $acc['ContactID'],
                    'full_name' => $acc['Name'] . '_' . ($acc['FirstName'] ?: '_') . '_' . ($acc['LastName'] ?: '_'),
                    'phone_number' => $acc['Phone'] ?? 0,
                    'is_mitra_trevel' => false,
                    'created_at' => now()->toDateTimeString(),
                    'updated_at' => now()->toDateTimeString(),
                ];
            }, $clean);

            $repo->batchUpsert($batch);
            $totalSaved += count($batch);

            Log::info("[Xero Sync] Page {$page} OK — fetched: {$totalFetched} | saved: {$totalSaved}");

            $this->updateCache('running', $page, $totalFetched, $totalSaved, $startedAt);

            if (count($contacts) < 100) {
                break;
            }

            $page++;
            usleep(1_100_000);
        }

        Cache::put(self::CACHE_KEY, [
            'status' => 'done',
            'total_fetched' => $totalFetched,
            'total_saved' => $totalSaved,
            'started_at' => $startedAt,
            'finished_at' => now()->toDateTimeString(),
        ], now()->addHours(2));

        Log::info("[Xero Sync] Selesai. Total fetched: {$totalFetched} | saved: {$totalSaved}");
    }

    public function failed(\Throwable $e): void
    {
        Cache::put(self::CACHE_KEY, [
            'status' => 'failed',
            'error' => $e->getMessage(),
            'failed_at' => now()->toDateTimeString(),
        ], now()->addHours(2));

        Log::error('[Xero Sync] Job failed: ' . $e->getMessage());
    }

    private function fetchPage(int $page): ?array
    {
        try {
            $response = Http::withHeaders([
                // ✅ FIX: Sekarang $this->accessToken dan $this->tenantId ada
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Xero-Tenant-Id' => $this->tenantId,
                'Accept' => 'application/json',
            ])->timeout(30)->get("{$this->xeroBaseUrl}/Contacts", [
                        'page' => $page,
                    ]);

            if ($response->status() === 429) {
                Log::warning("[Xero Sync] Rate limit 429 di page {$page}");
                return null;
            }

            if ($response->failed()) {
                Log::error("[Xero Sync] HTTP error page {$page}: " . $response->body());
                return null;
            }

            return $response->json()['Contacts'] ?? [];

        } catch (\Exception $e) {
            Log::error("[Xero Sync] Exception fetchPage {$page}: " . $e->getMessage());
            return null;
        }
    }

    private function mapContacts(array $contacts): array
    {
        return array_map(function ($c) {
            return [
                'ContactID' => $c['ContactID'] ?? null,
                'Name' => $c['Name'] ?? null,
                'FirstName' => $c['FirstName'] ?? null,
                'LastName' => $c['LastName'] ?? null,
                'EmailAddress' => $c['EmailAddress'] ?? null,
                'Phone' => $c['Phones'][0]['PhoneNumber'] ?? null,
                'IsCustomer' => $c['IsCustomer'] ?? false,
                'IsSupplier' => $c['IsSupplier'] ?? false,
                'ContactStatus' => $c['ContactStatus'] ?? 'ACTIVE',
            ];
        }, $contacts);
    }

    private function updateCache(string $status, int $page, int $fetched, int $saved, string $startedAt): void
    {
        Cache::put(self::CACHE_KEY, [
            'status' => $status,
            'current_page' => $page,
            'total_fetched' => $fetched,
            'total_saved' => $saved,
            'started_at' => $startedAt,
            'updated_at' => now()->toDateTimeString(),
        ], now()->addHours(2));
    }
}