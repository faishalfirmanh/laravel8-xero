<?php

namespace App\Http\Controllers\Xero;

use App\Http\Repository\MasterData\CoaRepo;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\ConfigRefreshXero;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Validator;
class CoaXeroController extends Controller
{

    private $xeroBaseUrl = 'https://api.xero.com/api.xro/2.0';

    use ConfigRefreshXero, ApiResponse;

    protected $repo;

    public function __construct(CoaRepo $repo)
    {
        $this->repo = $repo;
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

    public function getAllCoa(Request $request)
    {
        try {
            // Ambil Token Valid
            $tokenData = $this->getValidToken();
            if (!$tokenData) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Token Invalid/Expired. Silakan akses /xero/connect terlebih dahulu.'
                ], 401);
            }

            $response = Http::withHeaders($this->getHeaders())
                ->get($this->xeroBaseUrl . '/Accounts');


            if ($response->failed()) {
                Log::error('Xero API Error - Get All COA: ' . $response->body());

                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal mengambil data Chart of Accounts dari Xero',
                    'xero_status' => $response->status(),
                    'detail' => $response->json()
                ], $response->status());
            }

            $accounts = $response->json()['Accounts'] ?? [];

            // Optional: Mapping sederhana agar lebih clean
            $cleanAccounts = array_map(function ($acc) {
                return [
                    'AccountID' => $acc['AccountID'] ?? null,           // UUID penting
                    'Code' => $acc['Code'] ?? null,                // Nomor akun (1100, 1200, dll)
                    'Name' => $acc['Name'] ?? null,
                    'Type' => $acc['Type'] ?? null,                // BANK, CURRENT, REVENUE, EXPENSE, dll
                    'Status' => $acc['Status'] ?? null,
                    'CurrencyCode' => $acc['CurrencyCode'] ?? 'IDR',
                    'BankAccountNumber' => $acc['BankAccountNumber'] ?? null,
                    'Description' => $acc['Description'] ?? null,
                ];
            }, $accounts);

            $validator = Validator::make($request->all(), [
                'is_sync' => 'required|integer|in:0,1',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors(), 404);
            }


            if ($request->is_sync == 0) {
                return response()->json([
                    'status' => 'success',
                    'total' => count($accounts),
                    'data' => $cleanAccounts,     // versi clean
                    // 'raw_data'  => $accounts,          // uncomment jika ingin data mentah
                ]);
            } else {
                $savedCount = 0;
                foreach ($cleanAccounts as $acc) {
                    if (empty($acc['AccountID']) || empty($acc['Name'])) {
                        continue;
                    }

                    if ($acc['Type'] != 'BANK') {
                        $param_save = [
                            'account_type' => $acc['Type'],
                            'code' => $acc['Code'] ?? '-',
                            'name' => $acc['Name'],
                            'created_by' => auth()->id() ?? 0,
                            'desc' => mb_substr($acc['Description'] ?? '', 0, 500),
                            'account_uuid' => $acc['AccountID'],
                            'currency_code' => $acc['CurrencyCode'],
                            'status' => $acc['Status'] ? 1 : 0, // tambahan kolom jika ada
                        ];

                        $this->repo->firstCreate($param_save);
                        $savedCount++;
                    }

                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Berhasil sinkronisasi data COA dari Xero',
                    'total_fetched' => count($accounts),
                    'total_saved' => $savedCount,
                    'data' => $this->repo->getAllDataNoLimit(),
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Exception Get All COA: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil Chart of Accounts',
                'error' => $e->getMessage()
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
