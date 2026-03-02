<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use App\Traits\ApiResponse;
use App\ConfigRefreshXero;
use App\Models\MasterData\MasterCoa;
use App\Http\Repository\MasterData\MasterCoaRepository;

class MasterCoaController extends Controller
{
    use ApiResponse, ConfigRefreshXero;

    protected $repo;

    public function __construct(MasterCoaRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index()
    {
        return view('admin.master.master_coa');
    }

    public function getData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page'       => 'required|integer',
            'limit'      => 'required|integer',
            'keyword'    => 'nullable|string',
            'kolom_name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }

        $where = [];

        if ($request->keyword) {
            $data = $this->repo->searchData(
                $where,
                $request->limit,
                $request->page,
                $request->kolom_name,
                strtoupper($request->keyword)
            );
        } else {
            $data = $this->repo->getAllDataWithDefault(
                $where,
                $request->limit,
                $request->page,
                $request->kolom_name,
                'ASC'
            );
        }

        return $this->autoResponse($data);
    }

    public function syncXero()
    {
        try {
            $tokens = $this->getValidToken();

            if (!$tokens || !isset($tokens['access_token'])) {
                return $this->error('Token tidak valid atau belum login Xero', 401);
            }

            $accessToken = $tokens['access_token'];
            $tenantId    = $this->getTenantId($accessToken);

            if (!$tenantId) {
                return $this->error('Tenant ID tidak ditemukan', 400);
            }

            $headers = [
                'Authorization'  => 'Bearer ' . $accessToken,
                'Xero-tenant-id' => $tenantId,
                'Accept'         => 'application/json',
            ];

            // 1️⃣ GET ACCOUNTS
            $accountsResponse = Http::withHeaders($headers)
                ->get('https://api.xero.com/api.xro/2.0/Accounts');

            if (!$accountsResponse->successful()) {
                return $this->error($accountsResponse->body(), 400);
            }

            $accounts = $accountsResponse->json()['Accounts'] ?? [];

            // 2️⃣ GET TAX RATES
            $taxResponse = Http::withHeaders($headers)
                ->get('https://api.xero.com/api.xro/2.0/TaxRates');

            $taxRateMap = [];
            if ($taxResponse->successful()) {
                foreach ($taxResponse->json()['TaxRates'] ?? [] as $tax) {
                    if (isset($tax['TaxType'], $tax['EffectiveRate'])) {
                        $taxRateMap[$tax['TaxType']] = $tax['EffectiveRate'];
                    }
                }
            }

            // 3️⃣ GET PROFIT & LOSS
            $reportResponse = Http::withHeaders($headers)
                ->get('https://api.xero.com/api.xro/2.0/Reports/ProfitAndLoss');

            $ytdMap = [];
            if ($reportResponse->successful()) {
                $report = $reportResponse->json();

                if (isset($report['Reports'][0]['Rows'])) {
                    foreach ($report['Reports'][0]['Rows'] as $row) {
                        if (!isset($row['Rows'])) continue;

                        foreach ($row['Rows'] as $subRow) {
                            $accountName = $subRow['Cells'][0]['Value'] ?? null;
                            $amount      = $subRow['Cells'][1]['Value'] ?? 0;

                            if ($accountName) {
                                $ytdMap[$accountName] = $amount;
                            }
                        }
                    }
                }
            }

            // 4️⃣ SAVE TO DATABASE
            $data = $this->repo->syncFromXero($accounts, $taxRateMap, $ytdMap);

            return $this->autoResponse([
                'total' => $data->count(),
                'data'  => $data
            ]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    public function getCoaFromXeroLive(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page'       => 'required|integer',
            'limit'      => 'required|integer',
            'keyword'    => 'nullable|string',
            'kolom_name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        try {
            $tokens = $this->getValidToken();

            if (!$tokens || empty($tokens['access_token'])) {
                return $this->error('Token tidak valid atau belum login Xero', 401);
            }

            $accessToken = $tokens['access_token'];
            $tenantId    = $this->getTenantId($accessToken);

            if (!$tenantId) {
                return $this->error('Tenant ID tidak ditemukan', 500);
            }

            $headers = [
                'Authorization'  => 'Bearer ' . $accessToken,
                'Xero-tenant-id' => $tenantId,
                'Accept'         => 'application/json',
            ];

            $accountsResponse = Http::withHeaders($headers)
                ->get('https://api.xero.com/api.xro/2.0/Accounts');

            if (!$accountsResponse->successful()) {
                return $this->error($accountsResponse->body(), 400);
            }

            $accounts = $accountsResponse->json()['Accounts'] ?? [];

            $taxResponse = Http::withHeaders($headers)
                ->get('https://api.xero.com/api.xro/2.0/TaxRates');

            $taxRateMap = [];
            if ($taxResponse->successful()) {
                foreach ($taxResponse->json()['TaxRates'] ?? [] as $tax) {
                    if (isset($tax['TaxType'], $tax['EffectiveRate'])) {
                        $taxRateMap[$tax['TaxType']] = $tax['EffectiveRate'];
                    }
                }
            }

            $reportResponse = Http::withHeaders($headers)
                ->get('https://api.xero.com/api.xro/2.0/Reports/ProfitAndLoss');

            $ytdMap = [];
            if ($reportResponse->successful()) {
                $report = $reportResponse->json();

                if (isset($report['Reports'][0]['Rows'])) {
                    foreach ($report['Reports'][0]['Rows'] as $row) {
                        if (!isset($row['Rows'])) continue;

                        foreach ($row['Rows'] as $subRow) {
                            $accountName = $subRow['Cells'][0]['Value'] ?? null;
                            $amount      = $subRow['Cells'][1]['Value'] ?? 0;

                            if ($accountName) {
                                $ytdMap[$accountName] = $amount;
                            }
                        }
                    }
                }
            }
            $formatted = [];
            foreach ($accounts as $acc) {
                $name = $acc['Name'] ?? '';

                $formatted[] = [
                    'xero_account_id' => $acc['AccountID'] ?? '',
                    'code'        => $acc['Code'] ?? '',
                    'name'        => $name,
                    'description' => $acc['Description'] ?? '',
                    'type'        => $acc['Type'] ?? '',
                    'tax_type'    => $acc['TaxType'] ?? '',
                    'tax_rate'    => $taxRateMap[$acc['TaxType']] ?? 0,
                    'ytd'         => $ytdMap[$name] ?? 0,
                ];
            }

            // SEARCH
            if ($request->keyword) {
                $keyword = strtoupper($request->keyword);

                $formatted = array_filter($formatted, function ($row) use ($keyword) {
                    return str_contains(strtoupper($row['name']), $keyword);
                });

                $formatted = array_values($formatted);
            }

            // PAGINATION
            $total  = count($formatted);
            $offset = ($request->page - 1) * $request->limit;
            $data   = array_slice($formatted, $offset, $request->limit);

            return $this->autoResponse([
                'total' => $total,
                'data'  => $data
            ]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'code'        => 'required|string|max:10',
        'name'        => 'required|string|max:150',
        'description' => 'nullable|string',
        'type'        => 'required|string',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => false,
            'errors' => $validator->errors()
        ], 409);
    }

    try {

        $tokens = $this->getValidToken();
        if (!$tokens || empty($tokens['access_token'])) {
            return response()->json([
                'status' => false,
                'message' => 'Token tidak valid atau belum login Xero'
            ], 401);
        }

        $accessToken = $tokens['access_token'];
        $tenantId    = $this->getTenantId($accessToken);

        if (!$tenantId) {
            return response()->json([
                'status' => false,
                'message' => 'Tenant ID tidak ditemukan'
            ], 500);
        }

        $headers = [
            'Authorization'  => 'Bearer ' . $accessToken,
            'Xero-tenant-id' => $tenantId,
            'Accept'         => 'application/json',
            'Content-Type'   => 'application/json',
        ];

        $payload = [
            "Code" => trim($request->code),
            "Name" => trim($request->name),
            "Type" => strtoupper($request->type),
            "Status" => "ACTIVE",
        ];

        if ($request->filled('description')) {
            $payload["Description"] = trim($request->description);
        }

        \Log::info('XERO CREATE PAYLOAD:', $payload);

        $response = Http::withHeaders($headers)
            ->put('https://api.xero.com/api.xro/2.0/Accounts', $payload);

        if (!$response->successful()) {
            return response()->json([
                'status'  => false,
                'message' => 'Xero API Error',
                'debug'   => $response->json()
            ], $response->status());
        }

        $xeroData = $response->json()['Accounts'][0] ?? $response->json();

        return response()->json([
            'status'  => true,
            'message' => 'Account berhasil dibuat',
            'data'    => $xeroData
        ]);

    } catch (\Exception $e) {

        return response()->json([
            'status'  => false,
            'message' => $e->getMessage()
        ], 500);
    }
}
public function update(Request $request, $id)
{
    $validator = Validator::make($request->all(), [
        'name'        => 'nullable|string|max:150',
        'description' => 'nullable|string',
        'tax_type'    => 'nullable|string',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    try {

        $tokens = $this->getValidToken();
        if (!$tokens || empty($tokens['access_token'])) {
            return response()->json([
                'status' => false,
                'message' => 'Token tidak valid atau belum login Xero'
            ], 401);
        }

        $accessToken = $tokens['access_token'];
        $tenantId    = $this->getTenantId($accessToken);

        $headers = [
            'Authorization'  => 'Bearer ' . $accessToken,
            'Xero-tenant-id' => $tenantId,
            'Accept'         => 'application/json',
            'Content-Type'   => 'application/json',
        ];

        //ambil data lama
        $existingResponse = Http::withHeaders($headers)
            ->get("https://api.xero.com/api.xro/2.0/Accounts/{$id}");

        if (!$existingResponse->successful()) {
            return response()->json([
                'status'  => false,
                'message' => 'Gagal mengambil data account lama',
                'debug'   => $existingResponse->json()
            ], 500);
        }

        $existing = $existingResponse->json()['Accounts'][0];

        //merge data lama
    $payload = [
        "AccountID" => $id,
        "Code"      => $existing['Code'], // WAJIB ikut
        "Name"      => $request->name ?? $existing['Name'],
        "Type"      => $request->type ?? $existing['Type'], // kirim type baru kalau ada
        "TaxType"   => $request->tax_type ?? $existing['TaxType'],
    ];
        if ($request->filled('description')) {
            $payload["Description"] = $request->description;
        } else if (isset($existing['Description'])) {
            $payload["Description"] = $existing['Description'];
        }

        //update
        $response = Http::withHeaders($headers)
            ->post("https://api.xero.com/api.xro/2.0/Accounts", [
                "Accounts" => [$payload]
            ]);

        if (!$response->successful()) {
            return response()->json([
                'status'  => false,
                'message' => 'Xero API Error',
                'debug'   => $response->json()
            ], $response->status());
        }

        return response()->json([
            'status'  => true,
            'message' => 'Account berhasil diupdate',
            'data'    => $response->json()['Accounts'][0]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status'  => false,
            'message' => $e->getMessage()
        ], 500);
    }
}
public function getById(Request $request)
{
    $validator = Validator::make($request->all(), [
        'id' => 'required'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    $data = $this->repo->find($request->id);

    if (!$data) {
        return response()->json([
            'status' => false,
            'message' => 'Data tidak ditemukan'
        ], 404);
    }

    return response()->json([
        'status' => true,
        'data'   => $data
    ]);
}
public function storeLocal(Request $request)
{
    $validator = Validator::make($request->all(), [
        'code' => 'required|unique:master_coa,code',
        'name' => 'required|unique:master_coa,name',
        'type' => 'required',
        'tax_type' => 'required'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'errors' => $validator->errors()
        ], 422);
    }

    $data = MasterCoa::create([
        'xero_account_id' => null,
        'code' => $request->code,
        'name' => $request->name,
        'description' => $request->description,
        'type' => $request->type,
        'tax_type' => $request->tax_type,
        'ytd' => 0
    ]);

    return response()->json([
        'message' => 'Data local berhasil ditambahkan',
        'data' => $data
    ]);
}
public function updateLocal(Request $request, $id)
{
    $data = MasterCoa::find($id);

    if (!$data) {
        return response()->json([
            'message' => 'Data tidak ditemukan'
        ], 404);
    }

    $validator = Validator::make($request->all(), [
        'name' => 'required|unique:master_coa,name,' . $id,
        'type' => 'required',
        'tax_type' => 'required'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'errors' => $validator->errors()
        ], 422);
    }

    $data->update([
        'name' => $request->name,
        'description' => $request->description,
        'type' => $request->type,
        'tax_type' => $request->tax_type,
    ]);

    return response()->json([
        'message' => 'Data local berhasil diupdate',
        'data' => $data
    ]);
}
}