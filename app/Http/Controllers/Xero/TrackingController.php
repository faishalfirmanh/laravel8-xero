<?php

namespace App\Http\Controllers\Xero;

use App\Http\Repository\MasterData\TrackingRepo;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\ConfigRefreshXero;

use Validator;

class TrackingController extends Controller
{

    use ConfigRefreshXero, ApiResponse;

    private $repo;
    public function __construct(
        TrackingRepo $repo
    ) {
        $this->repo = $repo;
    }

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
            $list_data = [];
            $aa = 0;
            foreach ($getData["TrackingCategories"] as $key => $value) {
                if ($value["Name"] == "Agen") {//dev : Agent, //prod : Agen
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


    public function getKategory(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'type' => 'required|string|in:Divisi,Nama Paket',
            'is_sync' => 'required|numeric|in:0,1'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }

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
            $list_data = [];
            $aa = 0;
            if ($request->is_sync == 0) {
                foreach ($getData["TrackingCategories"] as $key => $value) {
                    if ($value["Name"] == $request->type) {
                        $list_data[$aa] = $value["Options"];
                        $aa++;
                    }
                }
                return response()->json($list_data);
            }
            $syncedCount = 0;

            foreach ($getData["TrackingCategories"] as $value) {

                // Step 1 — Simpan/update parent category, ambil primary key-nya
                $parent = $this->repo->CreateOrUpdate(
                    ['name_parent_category' => $value["Name"]],
                    null
                );
                $parentId = $parent->id;

                // Step 2 — Bangun lines_category dari Options milik kategori ini
                $lines = array_map(
                    function ($opt) use ($parentId) {
                        return [
                            'id_parent' => $parentId,
                            'item_name_category' => $opt["Name"],
                            'item_uuid_category' => $opt["TrackingOptionID"],
                        ];
                    },
                    $value["Options"] ?? []
                );

                // Step 3 — Update baris yang sama dengan lines_category (JSON)
                $this->repo->CreateOrUpdate(
                    ['lines_category' => $lines],   // ✅ array, bukan string JSON
                    $parentId
                );

                $syncedCount++;
            }

            return response()->json([
                'success' => true,
                'message' => 'Sinkronisasi berhasil',
                'synced' => $syncedCount,
            ]);


        } catch (\Exception $e) {
            return response()->json(['message' => 'Proxy Error: ' . $e->getMessage()], $e->getCode());
        }
    }


}
