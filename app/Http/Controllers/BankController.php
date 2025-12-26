<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\ConfigRefreshXero;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
class BankController extends Controller
{

    private $xeroBaseUrl = 'https://api.xero.com/api.xro/2.0';

    use ConfigRefreshXero;

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

    public function getAllBank()
    {
        try {
        // 1. Ambil Token Valid (Sesuai logic auth Anda)
            $tokenData = $this->getValidToken();

            if (!$tokenData) {
                return response()->json(['status' => 'error', 'message' => 'Token Invalid/Expired'], 401);
            }

            // 2. Tembak API Xero Accounts dengan Filter Type=="BANK"
            // Filter ini WAJIB agar tidak tercampur dengan akun Akuntansi lain
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

            $allAccounts = $response->json()['Accounts'];

            // 4. (Opsional) Mapping Data agar lebih mudah dibaca
            // Kita hanya ambil ID (UUID), Nama, dan Kode
            // $cleanList = array_map(function($bank) {
            //     return [
            //         'BankName'   => $bank['Name'],
            //         'BankCode'   => $bank['Code'], // Contoh: 1100
            //         'AccountID'  => $bank['AccountID'], // <--- INI UUID YANG ANDA BUTUHKAN UTK PAYMENT
            //         'Currency'   => $bank['CurrencyCode'] ?? 'IDR',
            //         'BankAccountNumber' => $bank['BankAccountNumber'] ?? '-'
            //     ];
            // }, $allAccounts);

            return response()->json([
                'status' => 'success',
                'total_banks' => count($allAccounts),
                'data' => $allAccounts
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }

    }

    public function postBankOverPayment(){
         $payloadOverpayment = [
            "BankTransactions" => [[
                "Type"        => "RECEIVE-OVERPAYMENT",
                "Contact"     => ["ContactID" => '31bac9bb-d7f8-4dfe-ac10-afdfed2d195f'],
                "BankAccount" => ["AccountID" => '23eda3ac-02dd-466e-9521-ba62feff6de7'],
                "Date"        => '2025-12-10',
                "Reference"   =>"referensi",
                "LineItems"   => [[
                    "Description" => "Overpayment pada Invoice " ." testing bbbbbb",
                    "UnitAmount"  => 401122,
                    // AccountCode kosongkan agar Xero otomatis pakai Accounts Receivable / AP
                    // Atau isi dengan kode akun Liability (Hutang ke Customer) jika ada
                ]]
            ]]
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
