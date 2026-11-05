<?php

namespace App\Jobs;

use App\ConfigRefreshXero;
use App\Http\Repository\MasterData\TrackingRepo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncXeroTrackingCategoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use ConfigRefreshXero;

    public $tries = 3;
    public $timeout = 120;
    public $backoff = [30, 120];

    protected $jobId;
    protected $dryRun;

    public function __construct($jobId, $dryRun = false)
    {
        $this->jobId = $jobId;
        $this->dryRun = (bool) $dryRun;
    }

    public static function cacheKey($jobId)
    {
        return 'sync_tracking_category:' . $jobId;
    }

    protected function setStatus($status, array $extra = [])
    {
        Cache::put(
            self::cacheKey($this->jobId),
            array_merge([
                'status' => $status,
                'job_id' => $this->jobId,
                'dry_run' => $this->dryRun,
                'updated_at' => now()->toDateTimeString(),
            ], $extra),
            now()->addHours(2)
        );
    }

    public function handle(TrackingRepo $repo)
    {
        $this->setStatus('running');

        // Token diambil di sini (bukan dikirim lewat constructor) supaya
        // tidak tersimpan di tabel jobs dan tidak expired saat antre.
        $tokenData = $this->getValidToken();
        if (!$tokenData) {
            $this->setStatus('failed', ['message' => 'Token kosong/invalid.']);
            return; // tidak di-retry, perlu login ulang ke Xero
        }

        $accessToken = $tokenData['access_token'];
        $tenantId = $this->getTenantId($accessToken);
        if (!$tenantId) {
            $this->setStatus('failed', ['message' => 'Tenant Xero tidak ditemukan.']);
            return;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Xero-Tenant-Id' => $tenantId,
            'Accept' => 'application/json',
        ])->timeout(60)->get('https://api.xero.com/api.xro/2.0/TrackingCategories', [
                    'includeArchived' => 'true',
                ]);

        // Rate limit -> kembalikan ke antrean sesuai Retry-After
        if ($response->status() === 429) {
            $wait = (int) $response->header('Retry-After');
            $this->setStatus('queued', ['message' => 'Kena rate limit Xero, dicoba ulang.']);
            $this->release($wait > 0 ? $wait : 60);
            return;
        }

        if (!$response->successful()) {
            // lempar exception -> otomatis retry sesuai $tries/$backoff
            throw new \RuntimeException(
                'Xero TrackingCategories HTTP ' . $response->status() . ': ' . $response->body()
            );
        }

        $categories = $response->json('TrackingCategories') ?: [];

        $result = [];
        $notFound = [];

        foreach ($categories as $category) {
            $name = isset($category['Name']) ? $category['Name'] : '';
            $options = isset($category['Options']) ? $category['Options'] : [];

            $merged = $repo->mergeLinesFromXero($name, $options, $this->dryRun);

            if ($merged === null) {
                $notFound[] = $name;
                continue;
            }

            $result[] = $merged;
        }

        $this->setStatus('done', [
            'result' => $result,
            'parent_not_in_db' => $notFound,
        ]);
    }

    public function failed(Throwable $e)
    {
        Log::error('SyncXeroTrackingCategoryJob gagal', ['error' => $e->getMessage()]);
        $this->setStatus('failed', ['message' => $e->getMessage()]);
    }
}