<?php

namespace App\Http\Controllers;

use App\Servics\ConfigXero;
use Illuminate\Http\Request;
use App\Models\DataJamaah;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\ConfigRefreshXero;
use Illuminate\Support\Facades\Log;

class ContactController extends Controller
{

  use ConfigRefreshXero;
    protected $configXero;

    public function __construct()
    {
        // $this->configXero = new ConfigXero();
    }

    public function viewContackForm()
    {
        return view('contact');
    }


    public function getContactLocal()
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
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
                'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
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
