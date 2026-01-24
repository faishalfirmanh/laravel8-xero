<?php

namespace App\Http\Controllers\Xero;

use App\Servics\ConfigXero;
use Illuminate\Http\Request;
use App\Models\DataJamaah;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\ConfigRefreshXero;
use Illuminate\Support\Facades\Log;
use App\Services\GlobalService;
class ContactController extends Controller
{

  use ConfigRefreshXero;
    protected $configXero;
      protected $globalService;
    public function __construct(GlobalService $globalService)
    {
        // $this->configXero = new ConfigXero();
        $this->globalService = $globalService;

    }

    public function viewContackForm()
    {
        return view('contact');
    }

    public function getContactsById(Request $request)
    {

    }

    public function getContactsSearch(Request $request)
    {
        try {
            $tokenData = $this->getValidToken();

            if (!$tokenData) {
                return response()->json([
                    'message' => 'Token kosong / invalid'
                ], 401);
            }
            // Ambil tenant ID secara dinamis (recommended)
            $tenantId = $this->getTenantId($tokenData['access_token']);
            if (!$tenantId) {
                return response()->json([
                    'message' => 'Tenant ID tidak ditemukan'
                ], 400);
            }

            $search = $request->query('name'); // ?name=andi
            $page   = (int) $request->query('page', 1);

            $query = [
                'page' => $page
            ];

            // Xero WHERE syntax
            if (!empty($search)) {
                $query['where'] = 'Name.Contains("' . addslashes($search) . '")';
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $tokenData['access_token'],
                'Xero-Tenant-Id' => $tenantId,
                'Accept' => 'application/json'
            ])->get('https://api.xero.com/api.xro/2.0/Contacts', $query);

            $available_min_req = (int) $response->header('X-MinLimit-Remaining');
            $available_day_req = (int) $response->header('X-DayLimit-Remaining');
            $this->globalService->requestCalculationXero($available_min_req, $available_day_req);

            // Jika Xero gagal
            if ($response->failed()) {
                return response()->json([
                    'message' => 'Xero API Error',
                    'status'  => $response->status(),
                    'body'    => $response->body()
                ], $response->status());
            }

            $view_req =  $this->globalService->getDataAvailabeRequestXero();
           return response()->json([
                'meta' => [
                    'request_min_tersisa_hari'  => $view_req->available_request_day,
                    'request_min_tersisa_menit' => $view_req->available_request_min,
                ],
                'data' => json_decode($response->body(), true)
            ], $response->status());

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Proxy Error',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function getContactLocal()//26122025 , durasi update / create 5 detik untuk 50 data,
    {
        // 1. Setup Resource Limit
        set_time_limit(120); // 2 Menit cukup untuk 50 data
        ini_set('memory_limit', '256M');

        try {
            // 2. Ambil Data Lokal (Limit 50 agar URL query Xero tidak kepanjangan)
            $dataLocal = DataJamaah::select(
                "id_jamaah", "no_ktp", "title", "tempat_lahir", "estimasi_berangkat",
                "leader", "id_status", "nama_jamaah", "alamat_jamaah",
                "hp_jamaah", "no_tlp", "created_at", "is_updated_to_xero",
                DB::raw("TRIM(SUBSTRING_INDEX(hp_jamaah, '/', 1)) as hp_jamaah_bersih")
            )
            ->where("is_updated_to_xero", false)
            ->limit(50) // JANGAN diubah jadi besar, karena limit URL character
            ->get();

            if ($dataLocal->isEmpty()) {
                return response()->json(['message' => 'Data jamaah sudah sinkron semua'], 200);
            }

            // 3. Validasi Token
            $tokenData = $this->getValidToken();
            if (!$tokenData) {
                return response()->json(['message' => 'Token kosong/invalid.'], 401);
            }

            $tenantId = env('XERO_TENANT_ID'); // Gunakan Config, bukan env()
            $accessToken = $tokenData['access_token'];

            // 4. --- OPTIMASI: SMART DUPLICATE CHECK ---
            // Alih-alih ambil semua data, kita buat query spesifik untuk 50 nama ini.
            // Format Xero Where: Name=="Ali" OR Name=="Budi" OR Name=="Citra"

            $localNames = $dataLocal->pluck('nama_jamaah')
                ->map(function($name) {
                    // Escape karakter kutip dua agar query tidak error
                    return 'Name=="' . str_replace('"', '\"', $name) . '"';
                })
                ->toArray();

            // Gabungkan dengan OR
            $whereClause = implode(' OR ', $localNames);

            // Request ke Xero (Cuma minta ID dan Name saja biar ringan)
            $existingContacts = [];
            if (!empty($whereClause)) {//cek nama jamaah apakah ada di xero
                $responseCheck = Http::withHeaders([
                    'Authorization'  => 'Bearer ' . $accessToken,
                    'Xero-Tenant-Id' => $tenantId,
                    'Accept'         => 'application/json',
                ])->get('https://api.xero.com/api.xro/2.0/Contacts', [
                    'where' => $whereClause,
                    'summaryOnly' => 'true' // PENTING: Response lebih kecil & cepat
                ]);

                if ($responseCheck->successful()) {
                    $xeroData = $responseCheck->json()['Contacts'] ?? [];
                    // Mapping Nama => ContactID
                    foreach ($xeroData as $xContact) {
                        $existingContacts[strtolower($xContact['Name'])] = $xContact['ContactID'];
                    }
                }
            }

            // 5. Siapkan Payload (Mixed: Create & Update)
            $payloadContacts = [];
            $ids_processed = [];

            foreach ($dataLocal as $jamaah) {
                $cleanName = trim($jamaah->nama_jamaah);
                $lowerName = strtolower($cleanName);

                $contactData = [
                    "Name" => $cleanName,
                    "DefaultCurrency" => "IDR",
                    "Addresses" => [
                        [
                            "AddressType" => "STREET",
                            "AddressLine1" => $jamaah->alamat_jamaah ?? "-"
                        ]
                    ],
                    "Phones" => [
                        [
                            "PhoneType" => "MOBILE",
                            "PhoneNumber" => $jamaah->hp_jamaah_bersih ?? ""
                        ]
                    ]
                ];

                // LOGIKA PENTING:
                // Jika nama sudah ada di Xero -> Masukkan ContactID (Xero akan melakukan UPDATE)
                // Jika belum ada -> Jangan masukkan ContactID (Xero akan melakukan CREATE)
                if (isset($existingContacts[$lowerName])) {
                    $contactData['ContactID'] = $existingContacts[$lowerName];
                }

                $payloadContacts[] = $contactData;
                $ids_processed[] = $jamaah->id_jamaah;
            }

            // 6. Kirim ke Xero (Batch POST)
            if (!empty($payloadContacts)) {
                $responsePost = Http::withHeaders([
                    'Authorization'  => 'Bearer ' . $accessToken,
                    'Xero-Tenant-Id' => $tenantId,
                    'Content-Type'   => 'application/json',
                ])->post('https://api.xero.com/api.xro/2.0/Contacts', [
                    'Contacts' => $payloadContacts
                ]);

                // 7. Cek Hasil dan Update DB Lokal
                if ($responsePost->successful()) {
                    // Update status di database lokal
                    // Kita asumsikan jika batch request sukses (200 OK), semua data di dalamnya aman
                    // Atau Xero mengembalikan warning tapi tetap 200.

                    DataJamaah::whereIn('id_jamaah', $ids_processed)
                        ->update(['is_updated_to_xero' => true]);

                    Log::info("Cron Job Contact: Sukses sync " . count($ids_processed) . " data. list ".json_encode($ids_processed));

                    return response()->json([
                        'status' => 'success',
                        'message' => 'Sync kontak berhasil',
                        'total_processed' => count($ids_processed),
                        'type' => 'Mixed (Create & Update)'
                    ]);

                } else {
                    // Handle Error
                    Log::error("Cron Job Contact Gagal: " . $responsePost->body());
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Gagal POST ke Xero',
                        'details' => $responsePost->json()
                    ], 400);
                }
            }

            return response()->json(['message' => 'Tidak ada payload yang terbentuk.'], 200);

        } catch (Exception $e) {
            Log::error("Cron Job Contact Fatal Error: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function getContactLocalv2()
    {
        // 1. Ambil Data Lokal yang belum sync
        $dataLocal = DataJamaah::select(
            "id_jamaah",
            "no_ktp",
            "title",
            "tempat_lahir",
            "estimasi_berangkat",
            "leader",
            "id_status",
            "nama_jamaah",
            "alamat_jamaah",
            "jenis_vaksin",
            "tgl_vaksin_1",
            "tgl_vaksin_2",
            "hp_jamaah",
            "no_tlp",
            // DB::raw untuk MySQL Cleaning HP
            DB::raw("TRIM(SUBSTRING_INDEX(hp_jamaah, '/', 1)) as hp_jamaah_bersih"),
            "keterangan",
            "created_at",
            "is_updated_to_xero"
        )
        ->where("is_updated_to_xero", false)
        ->limit(50)
        ->get();

        if ($dataLocal->isEmpty()) {
            return response()->json(['message' => 'Data jamaah sudah sinkron semua'], 200); // 200 OK lebih tepat daripada 404
        }

        // 2. Validasi Token
        $tokenData = $this->getValidToken();

        if (!$tokenData) {
            return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
        }

        $tenantId = env('XERO_TENANT_ID'); // Pastikan pakai config, bukan env() langsung

        // --- LOGIKA BARU: CEK DUPLIKASI ---
        // Daripada ambil SEMUA kontak Xero (masalah pagination), kita filter kontak Xero
        // hanya yang namanya ada di list data lokal kita.

        $localNames = $dataLocal->pluck('nama_jamaah')->toArray();


        $responseXero = Http::withHeaders([
            'Authorization' => 'Bearer ' . $tokenData["access_token"],
            'Xero-Tenant-Id' => $tenantId,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->get('https://api.xero.com/api.xro/2.0/Contacts');
        // Tambahkan ?page=1 dst jika ingin full check, tapi ini memperlambat.

        if ($responseXero->failed()) {
            return response()->json(['status' => 'error', 'message' => 'Gagal ambil data Xero'], 500);
        }

        $xeroContacts = $responseXero->json()['Contacts'];
        $existingXeroNames = [];
        foreach ($xeroContacts as $contact) {
            $existingXeroNames[] = strtolower($contact['Name']);
        }

        // 4. Filter & Siapkan Payload
        $newContactsPayload = [];
        $ids_to_update_status = []; // ID yang akan diubah statusnya jadi true (baik create baru maupun sudah ada)

        foreach ($dataLocal as $jamaah) {
            $namaJamaahLower = strtolower($jamaah->nama_jamaah);

            // Jika Nama SUDAH ADA di Xero
            if (in_array($namaJamaahLower, $existingXeroNames)) {
                // [PENTING] Tetap tandai sebagai "Updated" agar tidak diproses terus menerus
                $ids_to_update_status[] = $jamaah->id_jamaah;
                continue; // Skip pengiriman payload
            }

            // Jika BELUM ADA, siapkan payload
            // Simpan ID untuk update status NANTI SETELAH SUKSES POST
            // Kita pisahkan array ini agar jika POST gagal, status tidak terupdate dulu
            $ids_for_new_payload[] = $jamaah->id_jamaah;

            $newContactsPayload[] = [
                "Name" => $jamaah->nama_jamaah,
                "DefaultCurrency" => "IDR",
                "Addresses" => [
                    [
                        "AddressType" => "STREET",
                        "AddressLine1" => $jamaah->alamat_jamaah
                    ]
                ],
                "Phones" => [
                    [
                        "PhoneType" => "MOBILE",
                        "PhoneNumber" => $jamaah->hp_jamaah_bersih ?? ""
                    ]
                ]
            ];
        }

        $tot_new_data = count($newContactsPayload);

        // 5. Kirim ke Xero (Hanya data baru)
        if ($tot_new_data > 0) {
            $payload = ["Contacts" => $newContactsPayload];

            $saveResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $tokenData["access_token"],
                'Xero-Tenant-Id' => $tenantId,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post('https://api.xero.com/api.xro/2.0/Contacts', $payload);

            if ($saveResponse->successful()) {
                // Jika sukses, gabungkan ID data baru ke list ID yang akan diupdate statusnya
                Log::info("berhasil insert crob job dari al hidayah ke xero ContactkController line : 149");
                $ids_to_update_status = array_merge($ids_to_update_status, $ids_for_new_payload ?? []);
            } else {
                // Jika gagal kirim data baru, kita hanya update data yang MEMANG SUDAH ADA di Xero sebelumnya
                // (Opsional: atau return error full)
                Log::error("gagal insert crob job dari al hidayah ke xero ContactkController line : 154");
                if (!empty($ids_to_update_status)) {
                    DataJamaah::whereIn('id_jamaah', $ids_to_update_status)->update(['is_updated_to_xero' => true]);
                }

                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal simpan kontak baru ke Xero',
                    'details' => $saveResponse->body(),
                ], 400);
            }
        }

        // 6. Update Status Lokal (Mass Update)
        if (!empty($ids_to_update_status)) {
            DataJamaah::whereIn('id_jamaah', $ids_to_update_status)
                ->update(['is_updated_to_xero' => true]);
        }

        return response()->json([
            'status' => 'success',
            'message' => "Proses selesai. Data Baru: $tot_new_data. Total Status Terupdate: " . count($ids_to_update_status),
            'total_added' => $tot_new_data,
            'updated_ids' => $ids_to_update_status
        ], 200);
    }
     public function getContactLocalv1()
    {
        // 1. Ambil Data Lokal
       // dd(33);
        $dataLocal = DataJamaah::select(
            "id_jamaah",
            "no_ktp",
            "title",
            "tempat_lahir",
            "estimasi_berangkat",
            "leader",
            "id_status",
            "nama_jamaah",
            "alamat_jamaah",
            "jenis_vaksin",
            "tgl_vaksin_1",
            "tgl_vaksin_2",
            "hp_jamaah",
            "no_tlp",
            DB::raw("TRIM(SUBSTRING_INDEX(hp_jamaah, '/', 1)) as hp_jamaah_bersih"),
            "keterangan",
            "created_at",
            "is_updated_to_xero"
        )
        ->where("is_updated_to_xero",false)
        ->limit(50)
        ->get();

        if(count($dataLocal) < 1){
             return response()->json(['message' => 'data jamaah sudah semua'], 404);
        }

        $tokenData = $this->getValidToken();

        if (!$tokenData) {
            return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
        }

        $responseXero = Http::withHeaders([
            'Authorization' => 'Bearer ' . $tokenData["access_token"],
            'Xero-Tenant-Id' => env('XERO_TENANT_ID'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->get('https://api.xero.com/api.xro/2.0/Contacts');

        if ($responseXero->failed()) {
            return response()->json(['status' => 'error', 'message' => 'Gagal ambil data Xero'], 500);
        }

        $xeroContacts = $responseXero->json()['Contacts'];

        // 3. Buat List Nama Kontak Xero (Lower Case) untuk Pengecekan Cepat
        // Menggunakan array_column atau mapping agar mudah dicek
        $existingXeroNames = [];
        foreach ($xeroContacts as $contact) {
            $existingXeroNames[] = strtolower($contact['Name']);
        }

        // 4. Filter Data Lokal yang Belum Ada di Xero
        $newContactsPayload = [];
        $list_id_jamaah_updated = [];

        foreach ($dataLocal as $jamaah) {
            $namaJamaahLower = strtolower($jamaah->nama_jamaah);

            if (!in_array($namaJamaahLower, $existingXeroNames)) {
                $list_id_jamaah_updated[] = $jamaah["id_jamaah"];
                $newContactsPayload[] = [
                    "Name" => $jamaah->nama_jamaah, // Gunakan nama asli (bukan lower) untuk display
                    //"AccountNumber" => $jamaah->no_ktp,
                    // "id_jamaah" => $jamaah->id_jamaah,
                    "DefaultCurrency" => "IDR",
                    "Addresses" => [
                        [
                            "AddressType" => "STREET",
                            "AddressLine1" => $jamaah->alamat_jamaah
                        ]
                    ],
                    "Phones" => [
                        [
                            "PhoneType" => "MOBILE",
                            "PhoneNumber" => $jamaah->hp_jamaah_bersih ?? 00
                        ]
                    ]
                ];
            }else{
                continue;
            }
        }

        $tot_data = count($newContactsPayload);
        $responseData = [];
        // 5. Kirim ke Xero (BULK CREATE) jika ada data baru
        if ($tot_data > 0) {
            // Xero API bisa menerima array contacts sekaligus
            $payload = ["Contacts" => $newContactsPayload];

            $saveResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $tokenData["access_token"],
                'Xero-Tenant-Id' => env('XERO_TENANT_ID'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post('https://api.xero.com/api.xro/2.0/Contacts', $payload);

            if ($saveResponse->successful()) {
                $responseData = $saveResponse->json();
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal simpan ke Xero',
                    'details' => $saveResponse->body(),
                ], 400);
            }
        }
        if (!empty($ids_to_update_status)) {
            DataJamaah::whereIn('id_jamaah', $list_id_jamaah_updated)
                ->update(['is_updated_to_xero' => true]);
        }
        return response()->json([
            'status' => 'success',
            'message' => $tot_data > 0 ? "Berhasil menambahkan $tot_data kontak baru." : "Tidak ada kontak baru untuk ditambahkan.",
            'total_added' => $tot_data,
            'berhasil_updated'=>$list_id_jamaah_updated
        ], 200);
    }

    public function getContact(Request $request)
    {

        try {
            // $accessToken = $this->configXero->getValidAccessToken();
            // Panggilan dilakukan dari SISI SERVER, BUKAN BROWSER
            $tokenData = $this->getValidToken();
            if (!$tokenData) {
                return response()->json(['message' => 'Token kosong/invalid.'], 401);
            }
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $tokenData["access_token"],
                'Xero-Tenant-Id' => env('XERO_TENANT_ID'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->get('https://api.xero.com/api.xro/2.0/Contacts');

            // Mengembalikan respons Xero, termasuk status code (misalnya 200, 400, 401)
            return response()->json($response->json() ?: ['message' => 'Xero API Error'], $response->status());

        } catch (\Exception $e) {
            return response()->json(['message' => 'Proxy Error: ' . $e->getMessage()], 500);
        }
    }

    public function createContact(Request $request)
    {
        $payload = $request->json()->all();

        try {
            // Panggilan dilakukan dari SISI SERVER, BUKAN BROWSER
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
                'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post('https://api.xero.com/api.xro/2.0/Contacts', $payload);

            // Mengembalikan respons Xero, termasuk status code (misalnya 200, 400, 401)
            return response()->json($response->json() ?: ['message' => 'Xero API Error'], $response->status());

        } catch (\Exception $e) {
            return response()->json(['message' => 'Proxy Error: ' . $e->getMessage()], 500);
        }
    }



}
