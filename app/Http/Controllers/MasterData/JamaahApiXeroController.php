<?php

namespace App\Http\Controllers\MasterData;

use App\ConfigRefreshXero;
use App\Http\Controllers\Controller;
use App\Http\Repository\MasterData\JamaahAlhidRepository;
use Illuminate\Http\Request;
use Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Traits\ApiResponse;
use App\Http\Repository\MasterData\JamaahXeroRepository;
use App\Jobs\SyncXeroContactsJob;
use Illuminate\Support\Facades\Cache;
class JamaahApiXeroController extends Controller
{
    //

    protected $repo, $repo_alhid;
    use ApiResponse, ConfigRefreshXero;
    private $xeroBaseUrl = 'https://api.xero.com/api.xro/2.0';
    public function __construct(JamaahXeroRepository $repo, JamaahAlhidRepository $repo_alhid)
    {
        $this->repo = $repo;
        $this->repo_alhid = $repo_alhid;
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

    public function getSelect2JamaahAlhid(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'page' => 'required|integer',
            'keyword' => 'required|string',
            'kolom_name' => 'nullable|string',
            'limit' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }
        $where = [];
        $keyword = trim($request->keyword ?? '');

        $limit = (int) ($request->limit ?? 5);

        // Safety limit
        $limit = min(max($limit, 1), 10);
        $data = $this->repo_alhid->searchDataAlhidd(
            $where,
            $limit,
            0,
            'nama_jamaah',
            $keyword
        );

        return $this->autoResponse($data);
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
            $validator = Validator::make($request->all(), [
                'is_sync' => 'required|integer|in:0,1',
                'page' => 'nullable|integer|min:1',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), 422);
            }

            $tokenData = $this->getValidToken();
            if (!$tokenData) {
                return $this->errorResponse(
                    'Token Invalid/Expired. Silakan akses /xero/connect terlebih dahulu.',
                    [],
                    401
                );
            }

            // ── MODE PREVIEW: ambil 1 halaman saja, langsung return ──
            if ($request->is_sync == 0) {
                $page = $request->get('page', 1);
                $response = Http::withHeaders($this->getHeaders())
                    ->timeout(30)
                    ->get($this->xeroBaseUrl . '/Contacts', ['page' => $page]);

                if ($response->failed()) {
                    return $this->errorResponse(
                        'Gagal mengambil data Contacts dari Xero',
                        $response->json(),
                        $response->status()
                    );
                }

                $contacts = $response->json()['Contacts'] ?? [];

                return response()->json([
                    'status' => 'success',
                    'page' => $page,
                    'total' => count($contacts),
                    'data' => $contacts,
                ]);
            }

            // ── MODE SYNC: cek apakah ada job yang masih running ──
            $currentStatus = Cache::get(SyncXeroContactsJob::CACHE_KEY);
            if ($currentStatus && $currentStatus['status'] === 'running') {
                return response()->json([
                    'status' => 'already_running',
                    'message' => 'Sync sedang berjalan, pantau di GET /xero/contacts/sync-status',
                    'info' => $currentStatus,
                ], 409);
            }

            // ── Ambil access_token & tenant_id dari tokenData ──
            // Sesuaikan key ini dengan struktur return getValidToken() kamu
            $accessToken = $tokenData['access_token'];
            $tenantId = $this->getTenantId($accessToken);

            // ── Dispatch ke background queue ──
            SyncXeroContactsJob::dispatch($accessToken, $tenantId);

            return response()->json([
                'status' => 'sync_started',
                'message' => 'Job sync berjalan di background. Pantau via GET /xero/contacts/sync-status',
            ]);

        } catch (\Exception $e) {
            \Log::error('Exception getAllContact: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // ── Endpoint cek progress sync ──
    public function syncStatus()
    {
        $status = Cache::get(SyncXeroContactsJob::CACHE_KEY, [
            'status' => 'no_data',
            'message' => 'Belum ada sync yang pernah dijalankan.',
        ]);

        return response()->json($status);
    }

}
