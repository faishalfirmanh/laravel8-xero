<?php

namespace App\Http\Controllers\Xero;

use App\ConfigRefreshXero;
use App\Http\Controllers\Controller;
use App\Jobs\SyncBillJob;
use App\Models\SyncJobStatus;
use App\Services\GlobalService;
use App\Services\XeroRateLimitService;
use App\Traits\ApiResponse;
use Facade\FlareClient\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Log;
use Str;
use Validator;

class UserXeroController extends Controller
{
    use ApiResponse;
    protected $rateLimiter, $global;
    use ConfigRefreshXero;

    public function __construct(XeroRateLimitService $rateLimiter, GlobalService $global)
    {
        $this->rateLimiter = $rateLimiter;
        $this->global = $global;

    }

    public function getAll()
    {
        $tokenData = $this->getValidToken();
        $accessToken = $tokenData['access_token'];
        $tenantId = $this->getTenantId($accessToken);

        if (empty($tokenData) || empty($tokenData['access_token'])) {
            return response()->json([
                'success' => false,
                'message' => 'Access token Xero tidak tersedia',
                'data' => null,
            ], 401);
        }

        // ── Ambil semua User dari Xero ─────────────────────────────────────
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Xero-Tenant-Id' => $tenantId,
            'Accept' => 'application/json',
        ])->timeout(25)->get('https://api.xero.com/api.xro/2.0/Users');

        if ($response->status() === 429) {
            $retryAfter = (int) ($response->header('Retry-After') ?? 60);
            Log::warning("[SyncBillJob][index] Rate limited (429). Release {$retryAfter}s.");
            $this->triggerRelease($retryAfter);
            return null;
        }

        if (!$response->successful()) {
            Log::error("[SyncBillJob][index] Gagal fetch Users [{$response->status()}]: " . substr($response->body(), 0, 300));
            return null;
        }

        $users = $response->json('Users') ?? [];

        return collect($users)
            ->map(function ($user) {
                return [
                    'user_id' => $user['UserID'] ?? null,
                    'email' => $user['EmailAddress'] ?? null,
                    'first_name' => $user['FirstName'] ?? null,
                    'last_name' => $user['LastName'] ?? null,
                    'organisation_role' => $user['OrganisationRole'] ?? null,
                    'is_subscriber' => $user['IsSubscriber'] ?? false,
                ];
            })
            ->values()
            ->all();
    }

}
