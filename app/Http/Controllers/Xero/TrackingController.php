<?php

namespace App\Http\Controllers\Xero;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\ConfigRefreshXero;


class TrackingController extends Controller
{

   use ConfigRefreshXero;

   public function getAgent()
    {

       try {

            $tokenData = $this->getValidToken();
            if (!$tokenData) {
                return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
            }

            $getData = Http::withHeaders([
                'Authorization' => 'Bearer ' . $tokenData["access_token"],//env('BARER_TOKEN'), // Sebaiknya ganti ke config('xero.token') nanti
                'Xero-Tenant-Id' => env("XERO_TENANT_ID"),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->get("https://api.xero.com/api.xro/2.0/TrackingCategories");

            if ($getData->failed()) {
                return response()->json([
                    'error' => true,
                    'message' => 'Gagal get data',
                    'details' => $getData->json()
                ], $getData->status());
            }
            //  if($getData["TrackingCategories"]["Name"]);
            $list_data=[];
            $aa = 0;
            foreach ($getData["TrackingCategories"] as $key => $value) {
                if($value["Name"] == "Agen"){//dev : Agent, //prod : Agen
                    $list_data[$aa] = $value["Options"];
                    $aa++;
                }
            }
              return response()->json($list_data);
       } catch (\Throwable $e) {
              return response()->json(['message' => 'Proxy Error: ' . $e->getMessage()], $e->getCode());
       }
    }



      public function createBulkAgents()
    {
        try {
            $tokenData = $this->getValidToken();
            if (!$tokenData) {
                return response()->json(['message' => 'Token invalid.'], 401);
            }

            $headers = [
                'Authorization' => 'Bearer ' . $tokenData["access_token"],
                'Xero-Tenant-Id' => env("XERO_TENANT_ID"),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ];

            // --- LANGKAH 1: Cari TrackingCategoryID untuk nama "Agen" ---
            $getCategories = Http::withHeaders($headers)->get("https://api.xero.com/api.xro/2.0/TrackingCategories");

            $trackingCategoryId = null;
            foreach ($getCategories["TrackingCategories"] as $cat) {
                // Sesuaikan: "Agen" atau "Agent"
                if ($cat["Name"] == "Agen") {
                    $trackingCategoryId = $cat["TrackingCategoryID"];
                    break;
                }
            }

            if (!$trackingCategoryId) {
                return response()->json(['message' => 'Kategori "Agen" tidak ditemukan di Xero.'], 404);
            }

            // --- LANGKAH 2: Siapkan 120 data (Agen 1 sampai Agen 120) ---
            $options = [];
            for ($i = 1; $i <= 120; $i++) {
                $options[] = [
                    'Name' => 'Agen ' . $i
                ];
            }

            // --- LANGKAH 3: Push ke Xero ---
            $url = "https://api.xero.com/api.xro/2.0/TrackingCategories/{$trackingCategoryId}/Options";
            $response = Http::withHeaders($headers)->put($url, [
                'Options' => $options
            ]);

            if ($response->failed()) {
                return response()->json([
                    'error' => true,
                    'message' => 'Gagal insert ke Xero',
                    'details' => $response->json()
                ], $response->status());
            }

            return response()->json([
                'success' => true,
                'message' => '120 Agent berhasil ditambahkan ke kategori Agen',
                'total' => count($options)
            ]);

        } catch (\Throwable $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }


    public function getKategory()
    {

         try {
            $tokenData = $this->getValidToken();
            if (!$tokenData) {
                return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
            }
            $getData = Http::withHeaders([
                'Authorization' => 'Bearer ' . $tokenData["access_token"], // Sebaiknya ganti ke config('xero.token') nanti
                'Xero-Tenant-Id' => env("XERO_TENANT_ID"),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->get("https://api.xero.com/api.xro/2.0/TrackingCategories");

            if ($getData->failed()) {
                return response()->json([
                    'error' => true,
                    'message' => 'Gagal get data',
                    'details' => $getData->json()
                ], $getData->status());
            }
        //  if($getData["TrackingCategories"]["Name"]);
            $list_data=[];
            $aa = 0;
            foreach ($getData["TrackingCategories"] as $key => $value) {
                if($value["Name"] == "Divisi"){
                    $list_data[$aa] = $value["Options"];
                    $aa++;
                }
            }
            return response()->json($list_data);
         }catch (\Exception $e) {
            return response()->json(['message' => 'Proxy Error: ' . $e->getMessage()], $e->getCode());
        }
    }


}
