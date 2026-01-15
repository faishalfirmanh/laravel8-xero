<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use App\ConfigRefreshXero;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Xero\XeroContactController;

class FetchXeroContacts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, ConfigRefreshXero;

    public $tries = 5;
    public $timeout = 120;

    public function backoff()
    {
        return [60, 120, 300]; // auto backoff
    }

    public function handle()
    {
        $tokenData = $this->getValidToken();
        if (!$tokenData || empty($tokenData['access_token'])) {
            Log::error('Xero token invalid');
            return;
        }

        $tenantId = $this->getTenantId($tokenData['access_token']);
        // $tenantId = app()->make(XeroContactController::class)
        //     ->getTenantId($tokenData['access_token']);

        $response = Http::withHeaders([
            'Authorization'  => 'Bearer ' . $tokenData['access_token'],
            'Xero-Tenant-Id' => $tenantId,
            'Accept'         => 'application/json',
        ])->timeout(30)->get(
            'https://api.xero.com/api.xro/2.0/Contacts'
        );

        // HANDLE 429
        if ($response->status() === 429) {
            $retryAfter = (int) $response->header('Retry-After', 60);
            Log::warning('Xero 429 hit, retry after ' . $retryAfter);

            $this->release($retryAfter);
            return;
        }

        if (!$response->successful()) {
            Log::error('Xero API error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return;
        }

        $contacts = $response->json('Contacts') ?? [];

        Cache::put('xero_contacts', $contacts, now()->addMinutes(10));
    }
}
