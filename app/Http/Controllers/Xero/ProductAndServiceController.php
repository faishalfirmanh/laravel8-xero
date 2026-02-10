<?php

namespace App\Http\Controllers\Xero;
use Illuminate\Support\Facades\Log;
use App\ConfigRefreshXero;
use Illuminate\Http\Request;
use App\Services\XeroAuthService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
class ProductAndServiceController extends Controller
{
    use ConfigRefreshXero;
    public function viewProduct()
    {
        return view('product');
    }

    // private function getTenantId($token)
    // {
    //     $config = \App\Models\ConfigSettingXero::first();
    //     if ($config->xero_tenant_id)
    //         return $config->xero_tenant_id;

    //     $response = Http::withToken($token)->get('https://api.xero.com/connections');
    //     $tenantId = $response->json()[0]['tenantId'];
    //     $config->update(['xero_tenant_id' => $tenantId]);
    //     return $tenantId;
    // }

    public function getProductNoSame(Request $request)
    {
          try {
             $tokenData = $this->getValidToken();
            if (!$tokenData) {
               return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
            }

            $cleanIdInvoice = str_replace('"', '',  $request->invoice_id);
            $responseInvoice =  Http::withHeaders([
                'Authorization' => 'Bearer ' . $tokenData["access_token"],
                'Xero-Tenant-Id' => env('XERO_TENANT_ID'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->get("https://api.xero.com/api.xro/2.0/Invoices/$cleanIdInvoice");


            $list_item_rows = $responseInvoice->json()["Invoices"][0]["LineItems"];
            $code_item = [];
            foreach ($list_item_rows as $key => $value) {
              if(isset($value["ItemCode"])){ //jika tidak selected item
                  $code_item[] = $value["ItemCode"];
              }

            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $tokenData['access_token'],
                'Xero-Tenant-Id' => env('XERO_TENANT_ID'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->get('https://api.xero.com/api.xro/2.0/Items')->json()['Items'];

            $filtered_items = array_values(array_filter($response, function ($item) use ($code_item) {
                //return isset($item['Code']) && !in_array($item['Code'], $code_item);//fiter tidak duplicate
                return isset($item['Code']); //tampil semua
            }));
            return response()->json([
                'status' => 'success',
                'count_all' => count($response),
                'count_filtered' => count($filtered_items),
                'Items' => $filtered_items // Ini data yang ditampilkan
            ]);
            //return  $response->json();
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Detail Error: ' . $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    public function getProductAllNoBearer(Request $request,XeroAuthService $xeroService)
    {
        try {//$request->invoice_id
             $tokenData = $this->getValidToken();
            if (!$tokenData) {
               return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
            }

              $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $tokenData['access_token'],
                'Xero-Tenant-Id' => env('XERO_TENANT_ID'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->get('https://api.xero.com/api.xro/2.0/Items');
            return  $response->json();
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Detail Error: ' . $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
        // $tenantId = $this->getTenantId($token);
        // $response = Http::withToken($token)
        //     ->withHeaders(['xero-tenant-id' => $tenantId])
        //     ->get('https://api.xero.com/api.xro/2.0/Items');

        // return $response->json();
    }


   public function getProduct(Request $request)
    {
        try {
            $tokenData = $this->getValidToken();
            if (!$tokenData) {
                return response()->json(['message' => 'Token kosong/invalid'], 401);
            }

            $keyword =  $request->search;
            $tenantId = $this->getTenantId($tokenData['access_token']);

            // KONFIGURASI
            $limit = 20;           // frontend limit
            $xeroBatchSize = 100; // Xero limit tetap

            $frontendPage = max((int)$request->query('page', 1), 1);

            if ($frontendPage > 100) {
                return response()->json(['message' => 'Page terlalu besar (maks 100)'], 400);
            }

            $xeroPageTarget = (int) ceil(($frontendPage * $limit) / $xeroBatchSize);
            $offsetInBatch = (($frontendPage - 1) * $limit) % $xeroBatchSize;

            $cacheKey = "xero_items_{$tenantId}_page_{$xeroPageTarget}_" . md5($keyword);

            $allItems = Cache::remember($cacheKey, now()->addSeconds(60), function () use (
                    $tokenData,
                    $tenantId,
                    $xeroPageTarget,
                    $keyword
                ) {

                $params = [
                    'page' => $xeroPageTarget,
                ];

                if (!empty($keyword)) {
                    // Escape petik dua
                    $safeKeyword = str_replace(['\\', '"'], ['\\\\', '\"'], $keyword);

                    // PERBAIKAN PENTING DISINI:
                    // 1. Code menggunakan StartsWith (Xero sering menolak Contains untuk Code)
                    // 2. Name menggunakan Contains
                   $params['where'] = sprintf(
                        '(Code != null && Code.StartsWith("%s")) OR (Name != null && Name.Contains("%s"))',
                        $safeKeyword,
                        $safeKeyword
                    );
                }

                $response = Http::retry(1, 2000, function ($exception) {
                    return $exception->response &&
                        $exception->response->status() === 429;
                })->withHeaders([
                    'Authorization' => 'Bearer ' . $tokenData['access_token'],
                    'Xero-Tenant-Id' => $tenantId,
                    'Accept' => 'application/json',
                ])->get('https://api.xero.com/api.xro/2.0/Items', $params);

                // PERBAIKAN ERROR HANDLING (Agar tidak error "Wrong parameters")
                if ($response->failed()) {
                    // Ambil body error, pastikan string
                    $errorDetail = (string) $response->body();

                    // Kita throw string biasa saja agar Exception tidak bingung
                    throw new \Exception(
                        'Xero Error (' . $response->status() . '): ' . $errorDetail
                    );
                }

                return $response->json('Items') ?? [];
            });

            $pagedItems = array_slice($allItems, $offsetInBatch, $limit);

            $hasMore = count($pagedItems) === $limit &&
                (count($allItems) > ($offsetInBatch + $limit) || count($allItems) === 100);

            return response()->json([
                'current_page' => $frontendPage,
                'limit' => $limit,
                'total_in_page' => count($pagedItems),
                'has_more' => $hasMore,
                'data' => $pagedItems,
            ]);

        } catch (\Exception $e) {
            // Log error ke file laravel.log agar bisa dibaca detailnya tanpa merusak UI
            \Illuminate\Support\Facades\Log::error('Xero Search Error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Gagal mengambil data dari Xero',
                'error_snippet' => substr($e->getMessage(), 0, 200) . '...' // Potong pesan agar tidak terlalu panjang
            ], 500);
        }
    }

    //2025-01-23
    public function getProduct3(Request $request)
    {
        try {
            $tokenData = $this->getValidToken();
            if (!$tokenData) {
                return response()->json(['message' => 'Token kosong/invalid'], 401);
            }

            $keyword =  $request->search;
            //dd($keyword);
            $tenantId = $this->getTenantId($tokenData['access_token']);

            // KONFIGURASI
            $limit = 20;          // frontend limit
            $xeroBatchSize = 100; // Xero limit tetap

            $frontendPage = max((int)$request->query('page', 1), 1);

            // Batasi page supaya tidak deep pagination
            if ($frontendPage > 100) {
                return response()->json([
                    'message' => 'Page terlalu besar (maks 100)'
                ], 400);
            }

            // Mapping page frontend -> Xero page
            $xeroPageTarget = (int) ceil(($frontendPage * $limit) / $xeroBatchSize);
            $offsetInBatch = (($frontendPage - 1) * $limit) % $xeroBatchSize;

            // Cache per halaman Xero
            $cacheKey = "xero_items_{$tenantId}_page_{$xeroPageTarget}_" . md5($keyword);

            $allItems = Cache::remember($cacheKey, now()->addSeconds(60), function () use (
                    $tokenData,
                    $tenantId,
                    $xeroPageTarget,
                    $keyword
                ) {

            $params = [
                'page' => $xeroPageTarget,
            ];

            if (!empty($keyword)) {
               $safeKeyword = str_replace('"', '\"', $keyword);
                $params['where'] = sprintf(
                    'Code=="%s" OR Name.Contains("%s")',
                    $safeKeyword,
                    $safeKeyword
                );
            }

            $response = Http::retry(1, 2000, function ($exception) {
                return $exception->response &&
                    $exception->response->status() === 429;
            })->withHeaders([
                'Authorization' => 'Bearer ' . $tokenData['access_token'],
                'Xero-Tenant-Id' => $tenantId,
                'Accept' => 'application/json',
            ])->get('https://api.xero.com/api.xro/2.0/Items', $params);

            // if ($response->failed()) {
            //         throw new \Exception(
            //             'Xero API Error: ' . $response->status()
            //         );
            //     }

                return $response->json('Items') ?? [];
            });

            // Slice ke limit frontend
            $pagedItems = array_slice($allItems, $offsetInBatch, $limit);

            // Deteksi halaman berikutnya
            $hasMore = count($pagedItems) === $limit &&
                (count($allItems) > ($offsetInBatch + $limit) || count($allItems) === 100);

            return response()->json([
                'current_page' => $frontendPage,
                'limit' => $limit,
                'total_in_page' => count($pagedItems),
                'has_more' => $hasMore,
                'data' => $pagedItems,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Proxy Error',
                'Controller' => 'ProductAndServiceController',
                'line'=> $e->getLine(),
                'error' => $e->getMessage()
            ], 500);
        }
    }



    //before 15-01-2026
    public function getProduct2(Request $request)
    {
        try {
            $tokenData = $this->getValidToken();
            if (!$tokenData) {
                return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
            }

            $tenantId = $this->getTenantId($tokenData['access_token']);
            // --- KONFIGURASI LIMIT ---
            $limit = 10;           // Kita ingin limit 10
            $xeroBatchSize = 100;  // Xero selalu return 100

            // 1. Ambil input dari frontend
            $frontendPage = (int) $request->query('page', 1);
            $search = $request->query('search', '');

            // 2. LOGIKA MATEMATIKA: Konversi Halaman Frontend ke Halaman Xero
            // Contoh: User minta Page 2 (Item 11-20). Itu masih ada di Xero Page 1.

            // Rumus: Halaman Xero mana yang harus kita panggil?
            $xeroPageTarget = ceil(($frontendPage * $limit) / $xeroBatchSize);
            if ($xeroPageTarget < 1)
                $xeroPageTarget = 1;

            // Rumus: Mulai potong dari index ke berapa?
            // Contoh Page 2: ((2-1)*10) % 100 = 10. Kita mulai ambil dari index 10.
            $offsetInBatch = (($frontendPage - 1) * $limit) % $xeroBatchSize;

            // 3. Siapkan Query ke Xero
            $queryParams = [
                'page' => $xeroPageTarget, // Kirim halaman hasil hitungan, BUKAN halaman frontend
            ];

            //dd($search);
            if (!empty($search) || $search != null) {
                //old 12-01-2026
              // $queryParams['where'] = 'Code.Contains("' . $search . '") OR Name.Contains("' . $search . '")';
              $term = addslashes($search);
              $queryParams['where'] = "((Code != null AND Code.Contains(\"{$term}\")) OR (Name != null AND Name.Contains(\"{$term}\")))";
            }

            // 4. Panggil Xero API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $tokenData['access_token'],
                'Xero-Tenant-Id' => $tenantId,//env('XERO_TENANT_ID'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->get('https://api.xero.com/api.xro/2.0/Items', $queryParams);

            //dd($tenantId);
            Log::info('Xero Query Params:', $queryParams);
            Log::info('Full URL: https://api.xero.com/api.xro/2.0/Items?' . http_build_query($queryParams));
            if ($response->failed()) {
                Log::error('Xero error list product ');
                return response()->json(['message' => 'Xero API Error', 'details' => $response->json()
                ,'status'=>$response->status()], $response->status());
            }

            $xeroData = $response->json();
            $allItems = $xeroData['Items'] ?? [];

            // 5. SLICING DATA (Kunci agar terlimit 10)
            // Ambil array, mulai dari offset, ambil sebanyak $limit (10)
            $pagedItems = array_slice($allItems, $offsetInBatch, $limit);

            // 6. Cek apakah masih ada halaman selanjutnya
            // Ada next page jika:
            // a. Kita dapat full 10 item saat ini.
            // b. DAN (Masih ada sisa item di batch 100 ini ATAU batch ini penuh 100 yang artinya mungkin ada batch berikutnya)
            $hasMore = count($pagedItems) === $limit && (count($allItems) > ($offsetInBatch + $limit) || count($allItems) === 100);

            // 7. Kembalikan respons dengan struktur yang rapi
            return response()->json([
                'current_page' => $frontendPage,
                'limit' => $limit,
                'total_in_page' => count($pagedItems),
                'has_more' => $hasMore,
                'data' => $pagedItems, // Ini isinya array yang sudah dipotong jadi maks 10
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Proxy Error: ' . $e->getMessage()], 500);
        }
    }

    public function getProductById($productId)
    {
        try {

            $tokenData = $this->getValidToken();
            if (!$tokenData) {
                return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
            }

            // Panggilan dilakukan dari SISI SERVER, BUKAN BROWSER
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $tokenData['access_token'],//env('BARER_TOKEN'),
                'Xero-Tenant-Id' => env('XERO_TENANT_ID'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->get('https://api.xero.com/api.xro/2.0/Items/' . $productId);

            return response()->json($response->json() ?: ['message' => 'Xero API Error'], $response->status());
        } catch (\Exception $e) {
            return response()->json(['message' => 'Proxy Error: ' . $e->getMessage()], 500);
        }
    }

    public function createProduct(Request $request)
    {
        $payload = $request->json()->all();

        try {
            // Panggilan dilakukan dari SISI SERVER, BUKAN BROWSER

            $tokenData = $this->getValidToken();
            if (!$tokenData) {
                return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' .$tokenData['access_token'],// env('BARER_TOKEN'),
                'Xero-Tenant-Id' => env('XERO_TENANT_ID'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post('https://api.xero.com/api.xro/2.0/Items', $payload);

            // Mengembalikan respons Xero, termasuk status code (misalnya 200, 400, 401)
            return response()->json($response->json() ?: ['message' => 'Xero API Error'], $response->status());

        } catch (\Exception $e) {
            return response()->json(['message' => 'Proxy Error: ' . $e->getMessage()], 500);
        }
    }

    public function updateProduct(Request $request)
    {
        $payload = $request->json()->all();

        try {
            // Panggilan dilakukan dari SISI SERVER, BUKAN BROWSER
            $tokenData = $this->getValidToken();
            if (!$tokenData) {
                return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $tokenData['access_token'],// env('BARER_TOKEN'),
                'Xero-Tenant-Id' => env('XERO_TENANT_ID'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post('https://api.xero.com/api.xro/2.0/Items', $payload);

            // Mengembalikan respons Xero, termasuk status code (misalnya 200, 400, 401)
            return response()->json($response->json() ?: ['message' => 'Xero API Error'], $response->status());

        } catch (\Exception $e) {
            return response()->json(['message' => 'Proxy Error: ' . $e->getMessage()], 500);
        }
    }

}
