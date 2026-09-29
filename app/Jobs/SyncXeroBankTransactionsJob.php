<?php

namespace App\Jobs;

use App\Services\XeroBankSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncXeroBankTransactionsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800;          // 30 menit
    public $tries = 3;
    public $backoff = [60, 300];
    public $uniqueFor = 3600;        // cegah job ganda untuk akun yang sama

    /** @var string */
    public $accountId;

    /** @var string|null */
    public $from;

    /** @var string|null */
    public $to;

    public function __construct(string $accountId, ?string $from = null, ?string $to = null)
    {
        $this->accountId = $accountId;
        $this->from = $from;
        $this->to = $to;
    }

    public function uniqueId(): string
    {
        return $this->accountId;
    }

    public function handle(XeroBankSyncService $service): void
    {
        $service->progress($this->accountId, ['status' => 'running', 'page' => 0, 'created' => 0]);

        $result = $service->sync($this->accountId, $this->from, $this->to);

        $service->progress($this->accountId, ['status' => 'done'] + $result);
    }

    public function failed(\Throwable $e): void
    {
        app(XeroBankSyncService::class)->progress($this->accountId, [
            'status' => 'failed',
            'error' => $e->getMessage(),
        ]);
    }
}