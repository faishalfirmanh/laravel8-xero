<?php

namespace App\Http\Controllers\Xero;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
class AccountController extends Controller
{


   public function getAllAccount()
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


    public function getDetailAccount($id_invoice){
         $response_detail = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
            'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->get("https://api.xero.com/api.xro/2.0/Invoices/$id_invoice");

          return response()->json($response_detail->json(), $response_detail->status());
    }



}
