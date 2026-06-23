<?php

namespace App\Http\Controllers\Xero;

use App\ConfigRefreshXero;
use App\Http\Controllers\Controller;
use App\Jobs\SyncBillJob;
use App\Models\SyncJobStatus;
use App\Services\GlobalService;
use App\Services\XeroRateLimitService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Log;
use Str;
use Validator;

class XeroBillController extends Controller
{
    use ApiResponse;
    protected $rateLimiter;
    use ConfigRefreshXero;
    protected $global;

    private bool $shouldRelease = false;
    private int $releaseAfterSecs = 60;

    public function __construct(XeroRateLimitService $rateLimiter, GlobalService $global)
    {
        $this->rateLimiter = $rateLimiter;
        $this->global = $global;
    }

    /**
     * Cari Bank Account di Xero yang Code-nya kosong ("") atau "0000",
     * lalu generate random code 8 digit dan update balik ke Xero.
     *
     * BUKAN Chart of Account — endpoint /Accounts difilter Type=="BANK"
     * supaya hanya bank account yang diambil.
     */
    public function fixEmptyBankAccountCodes(): void
    {
        if ($this->shouldRelease) {
            return;
        }

        $tokenData = $this->getValidToken();
        $accessToken = $tokenData['access_token'];
        $tenantId = $this->getTenantId($accessToken);

        // ── 1. Ambil semua Bank Account dari Xero ──────────────────────────
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Xero-Tenant-Id' => $tenantId,
            'Accept' => 'application/json',
        ])->timeout(25)->get('https://api.xero.com/api.xro/2.0/Accounts', [
                    'where' => 'Type=="BANK"',
                ]);

        if ($response->status() === 429) {
            $retryAfter = (int) ($response->header('Retry-After') ?? 60);
            Log::warning("[SyncBillJob][fixEmptyBankAccountCodes] Rate limited (429). Release {$retryAfter}s.");
            $this->triggerRelease($retryAfter);
            return;
        }

        if (!$response->successful()) {
            Log::error("[SyncBillJob][fixEmptyBankAccountCodes] Gagal fetch Bank Accounts [{$response->status()}]: " . substr($response->body(), 0, 300));
            return;
        }

        $accounts = $response->json('Accounts') ?? [];

        // ── 2. Filter bank account yang Code-nya kosong / "0000" ───────────
        $emptyCodeBanks = array_filter($accounts, function ($acc) {
            $code = trim($acc['Code'] ?? '');
            return $code === '' || $code === '0000';
        });

        if (empty($emptyCodeBanks)) {
            Log::info('[SyncBillJob][fixEmptyBankAccountCodes] Tidak ada bank account dengan code kosong/0000.');
            return;
        }

        // Kumpulkan code yang sudah dipakai supaya random code baru tidak bentrok
        $usedCodes = collect($accounts)->pluck('Code')->filter()->map(fn($code) => (string) $code)->flip()->toArray();

        foreach ($emptyCodeBanks as $bank) {
            if ($this->shouldRelease) {
                break;
            }

            $accountId = $bank['AccountID'] ?? null;
            $accountName = $bank['Name'] ?? '(tanpa nama)';

            if (!$accountId) {
                continue;
            }

            // ── 3. Generate random 8 digit, pastikan unik dari code yang ada ──
            do {
                $newCode = (string) random_int(10000000, 99999999);
            } while (isset($usedCodes[$newCode]));

            $usedCodes[$newCode] = true;

            // ── 4. Update Code-nya di Xero ───────────────────────────────────
            $updateResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Xero-Tenant-Id' => $tenantId,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->timeout(25)->post("https://api.xero.com/api.xro/2.0/Accounts/$accountId", [
                        'Code' => $newCode,
                    ]);

            if ($updateResponse->status() === 429) {
                $retryAfter = (int) ($updateResponse->header('Retry-After') ?? 60);
                Log::warning("[SyncBillJob][fixEmptyBankAccountCodes] Rate limited (429) saat update $accountId. Release {$retryAfter}s.");
                $this->triggerRelease($retryAfter);
                return;
            }

            if (!$updateResponse->successful()) {
                Log::error("[SyncBillJob][fixEmptyBankAccountCodes] Gagal update code bank '$accountName' ($accountId) [{$updateResponse->status()}]: " . substr($updateResponse->body(), 0, 300));
                continue;
            }

            Log::info("[SyncBillJob][fixEmptyBankAccountCodes] Bank '$accountName' ($accountId) code diupdate ke $newCode.");

            usleep(300000); // throttle ringan antar update, sesuaikan dengan rate limit Xero
        }
    }

    private function triggerRelease(int $seconds): void
    {
        $this->shouldRelease = true;
        $this->releaseAfterSecs = max($this->releaseAfterSecs, $seconds);
    }


    public function getBills(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'is_sync' => 'required|in:0,1',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }

        $tokenData = $this->getValidToken();
        if (!$tokenData) {
            return response()->json(['message' => 'Token kosong/invalid.'], 401);
        }

        // ── is_sync = 1: insert ke DB lewat job (async, lewat queue) ───────────
        if ((int) $request->is_sync === 1) {
            $jobId = (string) Str::uuid();

            SyncJobStatus::create([
                'job_id' => $jobId,
                'status' => 'pending',
                'total_synced' => 0,
                'total_pages' => 0,
                'started_at' => now(),
                'job_type' => 'SyncXeroBillJob'
            ]);

            SyncBillJob::dispatch($tokenData, $jobId);

            return response()->json([
                'status' => 'pending',
                'job_id' => $jobId,
                'message' => 'Sync purchase bills dimulai. Gunakan job_id untuk cek status.',
            ]);
        }

        // ── is_sync = 0: GET langsung Purchase Bills dari Xero, TANPA insert ──
        $accessToken = $tokenData['access_token'];
        $tenantId = $this->getTenantId($accessToken);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Xero-Tenant-Id' => $tenantId,
            'Accept' => 'application/json',
        ])->timeout(25)->get('https://api.xero.com/api.xro/2.0/Invoices', [
                    'Statuses' => 'DRAFT,SUBMITTED,AUTHORISED,PAID',
                    // ACCPAY = Purchase Bill di Xero. Ini SATU-SATUNYA pembeda dari
                    // Sales Invoice (ACCREC) — endpoint-nya sama-sama /Invoices,
                    // ini quirk Xero API, bukan typo.
                    'where' => 'Type=="ACCPAY"',
                    'order' => 'Date DESC',
                    'page' => (int) $request->get('page', 1),
                    'unitdp' => 4,
                ]);

        if ($response->status() === 429) {
            return response()->json([
                'message' => 'Rate limit Xero tercapai, coba lagi beberapa saat.',
                'retry_after' => (int) ($response->header('Retry-After') ?? 60),
            ], 429);
        }

        if (!$response->successful()) {
            return response()->json([
                'message' => 'Gagal mengambil data Purchase Bills dari Xero.',
                'xero_status' => $response->status(),
            ], 502);
        }

        // Xero membungkus hasil ACCPAY (Purchase Bill) di key "Invoices" juga —
        // bukan karena ini Sales Invoice, itu cuma nama envelope JSON dari Xero.
        $purchaseBills = $response->json('Invoices') ?? [];

        $purchaseBills = array_values(array_filter(
            $purchaseBills,
            fn($inv) => ($inv['Type'] ?? null) === 'ACCPAY'
        ));

        return response()->json([
            'status' => 'preview',
            'page' => (int) $request->get('page', 1),
            'total' => count($purchaseBills),
            'purchase_bills' => $purchaseBills,
        ]);
    }
}
