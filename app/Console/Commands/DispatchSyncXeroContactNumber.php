<?php

namespace App\Console\Commands;

use App\ConfigRefreshXero;
use App\Jobs\SyncXeroContactNumberJob;
use App\Models\SyncJobStatus;
use App\Services\GlobalService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DispatchSyncXeroContactNumber extends Command
{
    protected $signature = 'xero:sync-contact-number';
    protected $description = 'Dispatch job backfill ContactNumber Xero dari DataJamaah.no_ktp';

    use ConfigRefreshXero;
    public function handle()
    {
        // 1) Cegah dispatch dobel — kalau masih ada job running/queued dalam 20 menit terakhir
        $running = SyncJobStatus::where('job_type', 'sync_xero_contact_number')
            ->whereIn('status', ['pending', 'running'])
            ->where('updated_at', '>=', Carbon::now()->subMinutes(20))
            ->exists();

        if ($running) {
            $this->warn('Job sync contact number masih berjalan/antri. Skip dispatch.');
            Log::info('[DispatchSyncXeroContactNumber] Skip — masih ada job aktif.');
            return 0;
        }

        // 2) Ambil token valid — sesuaikan dengan method di controller Xero Anda
        $tokenData = $this->getValidToken();
        if (!$tokenData || empty($tokenData['access_token'])) {
            $this->error('Access token Xero tidak tersedia.');
            Log::error('[DispatchSyncXeroContactNumber] Token Xero tidak tersedia.');
            return 1;
        }

        // 3) Buat record SyncJobStatus + dispatch
        $jobId = (string) Str::uuid();

        SyncJobStatus::create([
            'job_id' => $jobId,
            'job_type' => 'sync_xero_contact_number',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        SyncXeroContactNumberJob::dispatch($tokenData, $jobId);

        $this->info("Dispatched SyncXeroContactNumberJob. jobId={$jobId}");
        Log::info("[DispatchSyncXeroContactNumber] Dispatched jobId={$jobId}");

        return 0;
    }
}