<?php

namespace App\Http\Controllers\Xero;

use App\Http\Repository\MasterData\BankXeroRepo;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\ConfigRefreshXero;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Validator;
class BankController extends Controller
{

    private $xeroBaseUrl = 'https://api.xero.com/api.xro/2.0';

    use ConfigRefreshXero, ApiResponse;

    private $repo_bank_xero_local;
    public function __construct(BankXeroRepo $bankXeroRepo)
    {
        $this->repo_bank_xero_local = $bankXeroRepo;
    }

    private function getHeaders()
    {
        $tokenData = $this->getValidToken();
        if (!$tokenData) {
            return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
        }
        //dd($tokenData);
        return [
            'Authorization' => 'Bearer ' . $tokenData["access_token"],
            'Xero-Tenant-Id' => env("XERO_TENANT_ID"),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    public function getAllBank(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'is_sync' => 'nullable|numeric|in:1,0',
        ]);

        // Changed 500 to 422 for standard Validation Error response
        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        try {
            // 1. Ambil Token Valid
            $tokenData = $this->getValidToken();

            if (!$tokenData) {
                return response()->json(['status' => 'error', 'message' => 'Token Invalid/Expired'], 401);
            }

            // 2. Tembak API Xero Accounts
            $response = Http::withHeaders($this->getHeaders())->get('https://api.xero.com/api.xro/2.0/Accounts', [
                'where' => 'Type=="BANK"'
            ]);

            // 3. Cek Error dari Xero
            if ($response->failed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal ambil data Bank',
                    'detail' => $response->json()
                ], $response->status());
            }

            // Fallback to empty array if 'Accounts' is missing

            $responseData = $response->json();
            $allAccounts = $responseData['Accounts'] ?? [];

            $savedCount = 0;

            // Use strict comparison or cast to boolean/int if relying on '1' vs 1

            if ($request->is_sync == 1) {
                foreach ($allAccounts as $index => $acc) {

                    // 2. BULLETPROOF CHECK: Use array_key_exists or isset BEFORE reading the key
                    if (!isset($acc['AccountID']) || !isset($acc['Name'])) {
                        // Log the bad data so you can see exactly what Xero sent that caused the crash
                        \Log::warning("Xero Sync Skipped Index {$index}: Missing AccountID or Name", ['raw_data' => $acc]);
                        continue;
                    }

                    // 3. SAFE ASSIGNMENT: Since we passed the isset() check above, 'account_id' is safe.
                    // We use ?? for all other fields just in case Xero leaves them out.
                    $param_save = [
                        'account_id' => $acc['AccountID'],
                        'code' => $acc['Code'] ?? '-',
                        'name' => $acc['Name'],
                        'status' => (isset($acc['Status']) && $acc['Status'] === 'ACTIVE') ? 1 : 0,
                        'created_by' => auth()->id() ?? 0,
                        'type' => $acc['Type'] ?? 'BANK',
                        'currency_code' => $acc['CurrencyCode'] ?? null,
                        'account_number' => $acc['BankAccountNumber'] ?? '-',
                    ];

                    $this->repo_bank_xero_local->firstCreate($param_save);
                    $savedCount++;
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Berhasil sinkronisasi data Bank dari Xero',
                    'total_fetched' => count($allAccounts),
                    'total_saved' => $savedCount,
                    'data' => $this->repo_bank_xero_local->getAllDataNoLimit(),
                ]);
            }

            // Return raw data if not syncing
            return response()->json([
                'status' => 'success',
                'total_banks' => count($allAccounts),
                'data' => $allAccounts
            ]);

        } catch (\Exception $e) {
            // Good practice: log the actual error in Laravel so you can debug it later
            \Log::error('Xero Sync Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan pada server saat sinkronisasi Xero.',
                $e->getMessage()
                // Don't expose $e->getMessage() to the frontend in production to prevent leaking system paths/SQL errors
            ], 500);
        }
    }

    public function postBankOverPayment()
    {
        $payloadOverpayment = [
            "BankTransactions" => [
                [
                    "Type" => "RECEIVE-OVERPAYMENT",
                    "Contact" => ["ContactID" => '31bac9bb-d7f8-4dfe-ac10-afdfed2d195f'],
                    "BankAccount" => ["AccountID" => '23eda3ac-02dd-466e-9521-ba62feff6de7'],
                    "Date" => '2025-12-10',
                    "Reference" => "referensi",
                    "LineItems" => [
                        [
                            "Description" => "Overpayment pada Invoice " . " testing bbbbbb",
                            "UnitAmount" => 401122,
                            // AccountCode kosongkan agar Xero otomatis pakai Accounts Receivable / AP
                            // Atau isi dengan kode akun Liability (Hutang ke Customer) jika ada
                        ]
                    ]
                ]
            ]
        ];

        try {
            $resOver = Http::withHeaders($this->getHeaders())
                ->put($this->xeroBaseUrl . '/BankTransactions', $payloadOverpayment);
            // Cek Error
            if ($resOver->failed()) {
                Log::error("Gagal Restore Overpayment: " . $resOver->body());

                // PERBAIKAN 1: Return JSON error yang rapi agar terbaca di Postman/Frontend
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal kirim ke Xero',
                    'xero_response' => $resOver->json() // Tampilkan detail error Xero
                ], $resOver->status());
            }

            // Sukses
            Log::info("Sukses Restore Overpayment");

            return response()->json([
                'status' => 'success',
                'data' => $resOver->json()
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
