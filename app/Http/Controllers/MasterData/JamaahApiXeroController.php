<?php

namespace App\Http\Controllers\MasterData;

use App\ConfigRefreshXero;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Traits\ApiResponse;
use App\Http\Repository\MasterData\JamaahXeroRepository;

class JamaahApiXeroController extends Controller
{
    //

    protected $repo;
    use ApiResponse, ConfigRefreshXero;
    private $xeroBaseUrl = 'https://api.xero.com/api.xro/2.0';
    public function __construct(JamaahXeroRepository $repo)
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

    public function getAllSelect2(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'required|integer',
            'keyword' => 'nullable|string',
            'kolom_name' => 'nullable|string',
            'limit' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }
        $where = [];
        if ($request->keyword != null) {
            $data = $this->repo->searchData($where, $request->limit, $request->page, 'full_name', strtoupper($request->keyword));
        } else {
            $data = $this->repo->getAllDataWithDefault($where, $request->limit, $request->page, 'full_name', 'ASC');//getDataPaginate("name",10,$request->keyword);
        }
        return $this->autoResponse($data);
    }

    public function getAllContact(Request $request)
    {
        try {
            // Validasi optional
            $validator = Validator::make($request->all(), [
                'page' => 'nullable|integer|min:1',
                'is_sync' => 'required|integer|in:0,1',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), 422);
            }

            $page = $request->get('page', 1);

            // Ambil Token Valid
            $tokenData = $this->getValidToken();
            if (!$tokenData) {
                return $this->errorResponse('Token Invalid/Expired. Silakan akses /xero/connect terlebih dahulu.', [], 401);
            }

            // Request ke Xero API dengan limit 100
            $response = Http::withHeaders($this->getHeaders())
                ->get($this->xeroBaseUrl . '/Contacts', [
                    'page' => $page,
                    'limit' => 100,           // Maksimal 100 sesuai permintaan
                ]);

            if ($response->failed()) {
                \Log::error('Xero API Error - Get Contacts: ' . $response->body());

                return $this->errorResponse(
                    'Gagal mengambil data Contacts dari Xero',
                    $response->json(),
                    $response->status()
                );
            }

            $contacts = $response->json()['Contacts'] ?? [];

            // Optional: Clean / Mapping data
            $cleanContacts = array_map(function ($contact) {
                return [
                    'ContactID' => $contact['ContactID'] ?? null,
                    'Name' => $contact['Name'] ?? null,
                    'FirstName' => $contact['FirstName'] ?? null,
                    'LastName' => $contact['LastName'] ?? null,
                    'EmailAddress' => $contact['EmailAddress'] ?? null,
                    'Phone' => $contact['Phones'][0]['PhoneNumber'] ?? null, // ambil nomor utama
                    'IsCustomer' => $contact['IsCustomer'] ?? false,
                    'IsSupplier' => $contact['IsSupplier'] ?? false,
                    'ContactStatus' => $contact['ContactStatus'] ?? 'ACTIVE',
                    'CompanyNumber' => $contact['CompanyNumber'] ?? null,
                    'Addresses' => $contact['Addresses'] ?? [],
                ];
            }, $contacts);

            if ($request->is_sync == 0) {
                return response()->json([
                    'status' => 'success',
                    'total' => count($contacts),
                    'data' => $cleanContacts,     // versi clean
                    // 'raw_data'  => $accounts,          // uncomment jika ingin data mentah
                ]);
            } else {

                $savedCount = 0;
                foreach ($cleanContacts as $acc) {

                    $first = isset($acc["FirstName"]) ? $acc["FirstName"] : "_";
                    $last = isset($acc["LastName"]) ? $acc["LastName"] : "_";
                    $full_name = $acc["Name"] . "_" . $first . "_" . $last;
                    $cek_phone = isset($acc["Phones"][3]["PhoneNumber"]) ? $acc["Phones"][3]["PhoneNumber"] : 0;
                    $param_create_jmaah = ['uuid_contact' => $acc['ContactID'], 'full_name' => $full_name, 'phone_number' => $cek_phone, 'is_mitra_trevel' => false];
                    $this->repo->firstCreate($param_create_jmaah);
                    $savedCount++;

                }

                return response()->json([
                    'status' => 'insert success',
                    'total' => count($contacts),
                    'data' => $cleanContacts,     // versi clean
                    // 'raw_data'  => $accounts,          // uncomment jika ingin data mentah
                ]);
            }



        } catch (\Exception $e) {
            \Log::error('Exception Get All Contacts: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil contact',
                'error' => $e->getMessage()
            ], 500);
        }
    }


}
