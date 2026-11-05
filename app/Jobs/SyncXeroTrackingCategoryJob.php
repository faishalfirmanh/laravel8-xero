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

    protected function logTo($level, $message, array $context = [])
    {
        Log::log($level, '[TrackingSync] ' . $message, array_merge([
            'job_id' => $this->jobId,
            'attempt' => $this->attempts(),
            'dry_run' => $this->dryRun,
        ], $context));
    }

    public function handle(TrackingRepo $repo)
    {
        $startedAt = microtime(true);

        $this->setStatus('running');
        $this->logTo('info', 'START job dimulai');

        // 1. Token
        $tokenData = $this->getValidToken();
        if (!$tokenData) {
            $this->logTo('error', 'Token Xero kosong/invalid, job dihentikan (perlu login ulang Xero)');
            $this->setStatus('failed', ['message' => 'Token kosong/invalid.']);
            return;
        }
        $this->logTo('info', 'Token Xero OK');

        // 2. Tenant
        $accessToken = $tokenData['access_token'];
        $tenantId = $this->getTenantId($accessToken);
        if (!$tenantId) {
            $this->logTo('error', 'Tenant Xero tidak ditemukan, job dihentikan');
            $this->setStatus('failed', ['message' => 'Tenant Xero tidak ditemukan.']);
            return;
        }
        $this->logTo('info', 'Tenant Xero OK', ['tenant_id' => $tenantId]);

        // 3. Request ke Xero
        $this->logTo('info', 'Request GET /TrackingCategories ke Xero (includeArchived=true)');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Xero-Tenant-Id' => $tenantId,
            'Accept' => 'application/json',
        ])->timeout(60)->get('https://api.xero.com/api.xro/2.0/TrackingCategories', [
                    'includeArchived' => 'true',
                ]);

        $this->logTo('info', 'Response Xero diterima', ['http_status' => $response->status()]);

        if ($response->status() === 429) {
            $wait = (int) $response->header('Retry-After');
            $wait = $wait > 0 ? $wait : 60;
            $this->logTo('warning', 'Kena rate limit Xero (429), dikembalikan ke antrean', ['retry_after_detik' => $wait]);
            $this->setStatus('queued', ['message' => 'Kena rate limit Xero, dicoba ulang.']);
            $this->release($wait);
            return;
        }

        if (!$response->successful()) {
            $this->logTo('error', 'Xero mengembalikan error, akan di-retry bila masih ada sisa percobaan', [
                'http_status' => $response->status(),
                'body' => substr($response->body(), 0, 500),
            ]);
            throw new \RuntimeException(
                'Xero TrackingCategories HTTP ' . $response->status() . ': ' . $response->body()
            );
        }

        // 4. Merge per kategori
        $categories = $response->json('TrackingCategories') ?: [];

        $this->logTo('info', 'Jumlah kategori dari Xero: ' . count($categories));

        $result = [];
        $notFound = [];

        foreach ($categories as $category) {
            $name = isset($category['Name']) ? $category['Name'] : '';
            $options = isset($category['Options']) ? $category['Options'] : [];

            $this->logTo('info', 'Proses kategori "' . $name . '"', [
                'status_xero' => isset($category['Status']) ? $category['Status'] : null,
                'total_option' => count($options),
            ]);

            $merged = $repo->mergeLinesFromXero($name, $options, $this->dryRun);

            if ($merged === null) {
                $notFound[] = $name;
                $this->logTo('warning', 'Kategori "' . $name . '" tidak ada di tabel tracking_categories, dilewati');
                continue;
            }

            $result[] = $merged;

            $this->logTo('info', 'Merge selesai untuk "' . $name . '"', [
                'id_parent' => $merged['id_parent'],
                'total_xero' => $merged['total_xero'],
                'ditambahkan' => $merged['added_count'],
                'nama_berbeda' => count($merged['name_different']),
                'disimpan_ke_db' => !$this->dryRun && $merged['added_count'] > 0,
            ]);

            if ($merged['added_count'] > 0) {
                $this->logTo('info', 'Option baru "' . $name . '" (maks 30 ditampilkan)', [
                    'option_baru' => array_slice(array_column($merged['added'], 'item_name_category'), 0, 30),
                ]);
            }

            if (!empty($merged['name_different'])) {
                $this->logTo('warning', 'Nama option di Xero berbeda dengan DB (tidak ditimpa, maks 30 ditampilkan)', [
                    'detail' => array_slice($merged['name_different'], 0, 30),
                ]);
            }
        }

        $this->setStatus('done', [
            'result' => $result,
            'parent_not_in_db' => $notFound,
        ]);

        $this->logTo('info', 'SELESAI', [
            'durasi_detik' => round(microtime(true) - $startedAt, 2),
            'parent_not_in_db' => $notFound,
        ]);
    }

    public function failed(Throwable $e)
    {
        $this->logTo('error', 'GAGAL final setelah semua percobaan', [
            'error' => $e->getMessage(),
            'file' => $e->getFile() . ':' . $e->getLine(),
        ]);
        $this->setStatus('failed', ['message' => $e->getMessage()]);
    }
}