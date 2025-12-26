<?php

namespace App\Http\Controllers;

use App\ConfigRefreshXero;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
class PaymentController extends Controller
{

     use ConfigRefreshXero;

     public function getGroupedAccounts()
    {
        $accessToken = env('BARER_TOKEN');
        $tenantId = env('XERO_TENANT_ID');

        try {

            $tokenData = $this->getValidToken();
            if (!$tokenData) {
                return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $tokenData["access_token"],
                'Xero-Tenant-Id' => $tenantId,
                'Accept' => 'application/json'
                ])->get('https://api.xero.com/api.xro/2.0/Accounts', [
                    'where' => 'Status=="ACTIVE"'
            ]);

            if ($response->failed()) {
                return response()->json(['error' => 'Gagal fetch Accounts'], 500);
            }

            $accounts = $response->json()['Accounts'];

            $grouped = [];


            ksort($accounts);

            return response()->json([
                'Status' => 'OK',
                'GroupedAccounts' => $accounts
            ]);

        } catch (\Throwable $e) {
             return response()->json(['message' => 'Proxy Error: ' . $e->getMessage()], 500);
        }

        // Filter: Status harus ACTIVE
    }

   public function updatePaymentStatus($payment_id, $status = "DELETED")
    {
        $payload = [
            "Status" => $status,
        ];

        $inv = [];
        $update_payment = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('BARER_TOKEN'), // Sebaiknya ganti ke config('xero.token') nanti
            'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post("https://api.xero.com/api.xro/2.0/Payments/$payment_id", $payload);

        // Cek error handling agar lebih detail di log jika gagal
        if ($update_payment->failed()) {
            return response()->json([
                'error' => true,
                'message' => 'Gagal Void Payment',
                'details' => $update_payment->json()
            ], $update_payment->status());
        }

        return response()->json($update_payment->json(), $update_payment->status());
    }


    public function getDetailInvoice($id_invoice){
         $response_detail = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
            'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->get("https://api.xero.com/api.xro/2.0/Invoices/$id_invoice");

          return response()->json($response_detail->json(), $response_detail->status());
    }



}
