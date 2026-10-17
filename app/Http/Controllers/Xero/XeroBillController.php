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

    public function fixEmptyBankAccountNumbers(): void
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
            Log::warning("[SyncBillJob][fixEmptyBankAccountNumbers] Rate limited (429). Release {$retryAfter}s.");
            $this->triggerRelease($retryAfter);
            return;
        }

        if (!$response->successful()) {
            Log::error("[SyncBillJob][fixEmptyBankAccountNumbers] Gagal fetch Bank Accounts [{$response->status()}]: " . substr($response->body(), 0, 300));
            return;
        }

        $accounts = $response->json('Accounts') ?? [];

        // ── 2. Filter bank account yang BankAccountNumber-nya kosong / "0000" ──
        $emptyNumberBanks = array_filter($accounts, function ($acc) {
            $number = trim($acc['BankAccountNumber'] ?? '');
            return $number === '' || $number === '0000';
        });

        if (empty($emptyNumberBanks)) {
            Log::info('[SyncBillJob][fixEmptyBankAccountNumbers] Tidak ada bank account dengan account number kosong/0000.');
            return;
        }

        // Kumpulkan number yang sudah dipakai supaya random tidak bentrok
        $usedNumbers = collect($accounts)
            ->pluck('BankAccountNumber')
            ->filter()
            ->map(fn($n) => (string) $n)
            ->flip()
            ->toArray();

        foreach ($emptyNumberBanks as $bank) {
            if ($this->shouldRelease) {
                break;
            }

            $accountId = $bank['AccountID'] ?? null;
            $accountName = $bank['Name'] ?? '(tanpa nama)';

            if (!$accountId) {
                continue;
            }

            // ── 3. Generate random 8 digit, pastikan unik ──────────────────
            do {
                $newNumber = (string) random_int(10000000, 99999999);
            } while (isset($usedNumbers[$newNumber]));

            $usedNumbers[$newNumber] = true;

            // ── 4. Update BankAccountNumber-nya di Xero ────────────────────
            $updateResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Xero-Tenant-Id' => $tenantId,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->timeout(25)->post("https://api.xero.com/api.xro/2.0/Accounts/$accountId", [
                        'BankAccountNumber' => $newNumber,
                    ]);

            if ($updateResponse->status() === 429) {
                $retryAfter = (int) ($updateResponse->header('Retry-After') ?? 60);
                Log::warning("[SyncBillJob][fixEmptyBankAccountNumbers] Rate limited (429) saat update $accountId. Release {$retryAfter}s.");
                $this->triggerRelease($retryAfter);
                return;
            }

            if (!$updateResponse->successful()) {
                Log::error("[SyncBillJob][fixEmptyBankAccountNumbers] Gagal update account number bank '$accountName' ($accountId) [{$updateResponse->status()}]: " . substr($updateResponse->body(), 0, 300));
                continue;
            }

            Log::info("[SyncBillJob][fixEmptyBankAccountNumbers] Bank '$accountName' ($accountId) account number diupdate ke $newNumber.");

            usleep(300000); // throttle ringan antar update
        }
    }

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


    public function fetchBillDetail(string $billUuid)
    {
        try {
            $tokenData = $this->getValidToken();
            $accessToken = $tokenData['access_token'];
            $tenantId = $this->getTenantId($accessToken);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Xero-Tenant-Id' => $tenantId,
                'Accept' => 'application/json',
            ])
                ->timeout(25)
                ->get(
                    'https://api.xero.com/api.xro/2.0/Invoices/' . $billUuid,
                    [
                        'unitdp' => 4,
                    ]
                );

            // Tangani rate limit Xero
            if ($response->status() === 429) {
                Log::warning(
                    "[SyncBillJob] Rate limit Xero saat fetch bill {$billUuid}"
                );

                return response()->json([
                    'success' => false,
                    'message' => 'Batas request API Xero tercapai. Silakan coba lagi.',
                ], 429);
            }

            // Tangani error HTTP lainnya
            if (!$response->successful()) {
                Log::error(
                    "[SyncBillJob] Fetch bill detail {$billUuid} gagal " .
                    "[{$response->status()}]: " .
                    substr($response->body(), 0, 500)
                );

                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mengambil detail bill dari Xero.',
                    'status' => $response->status(),
                    'error' => $response->json() ?: $response->body(),
                ], $response->status());
            }

            // Ambil data JSON dari response Xero
            $result = $response->json();

            $bill = $result['Invoices'][0] ?? null;

            if (!$bill) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bill tidak ditemukan di Xero.',
                ], 404);
            }

            // Pastikan transaksi memang bill (Accounts Payable)
            if (($bill['Type'] ?? null) !== 'ACCPAY') {
                return response()->json([
                    'success' => false,
                    'message' => 'Data ditemukan, tetapi bukan bill pemasok.',
                    'type' => $bill['Type'] ?? null,
                ], 422);
            }

            return response()->json([
                'success' => true,
                'data' => $bill,
            ]);

        } catch (\Throwable $e) {
            Log::error(
                "[SyncBillJob] Exception fetch bill detail {$billUuid}: " .
                $e->getMessage()
            );

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil detail bill.',
            ], 500);
        }
    }


    //contoh input month_filter : 02-2026
    public function getBills(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'is_sync' => 'required|in:0,1',
            'is_all' => 'required|between:0,1',
            'month_filter' => 'required_if:is_all,0|nullable|date_format:m-Y',//'month_filter'=> 'date'//ex, 02-2026, filter semua bill kusus bulan 6, jika is_all = 0, jika is_all =1, abaikan 
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }

        $tokenData = $this->getValidToken();
        if (!$tokenData) {
            return response()->json(['message' => 'Token kosong/invalid.'], 401);
        }
        $monthFilter = ((int) $request->is_all === 0) ? $request->month_filter : null;
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

            SyncBillJob::dispatch($tokenData, $jobId, $monthFilter);

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
