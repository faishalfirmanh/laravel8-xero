<?php
namespace App\Http\Controllers\Transaction\Sales;

use App\Http\Controllers\Controller;
use App\Http\Repository\Revenue\InvoiceDXeroLocalRepo;
use App\Http\Repository\Transaction\TransCoaRepo;
use Illuminate\Http\Request;
use App\Http\Repository\Revenue\InvoiceXeroLocalRepo;
use App\Http\Repository\MasterData\DataJamaahXeroRepository;
use App\Http\Repository\Revenue\HotelDetailInvoicesRepository;
use Validator;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;
use App\Services\GlobalService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\PaymentParams;
use Illuminate\Support\Facades\Http;
use App\ConfigRefreshXero;
use App\Models\Revenue\Hotel\DetailInvoicesHotel;
use App\Models\Revenue\Hotel\InvoicesHotel;
use App\Models\Config\ConfigCurrency;
use Barryvdh\DomPDF\Facade\Pdf;

class InvXeroController extends Controller
{

    private $xeroBaseUrl = 'https://api.xero.com/api.xro/2.0';
    protected $repo, $repo_detail, $service_global, $repo_jamaah, $repo_all_trans;
    use ConfigRefreshXero;
    use ApiResponse;


    public function __construct(
        InvoiceXeroLocalRepo $repo,
        InvoiceDXeroLocalRepo $repo_detail,
        TransCoaRepo $repo_all_trans,

        GlobalService $service_global,
        DataJamaahXeroRepository $repo_jamaah
    ) {
        $this->repo = $repo;
        $this->repo_detail = $repo_detail;
        $this->repo_all_trans = $repo_all_trans;
        $this->service_global = $service_global;
        $this->repo_jamaah = $repo_jamaah;
    }

    public function getListInvoice(Request $request)
    {


    }


    public function getAllPaginate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'required|integer',
            'keyword' => 'nullable|string',
            'kolom_name' => 'required|string',
            'limit' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }
        $where = [];
        if ($request->keyword != null) {
            $data = $this->repo->searchData($where, $request->limit, $request->page, 'contact_name', strtoupper($request->keyword));
        } else {
            $data = $this->repo->getAllDataWithDefault($where, $request->limit, $request->page, 'contact_name', 'ASC');//getDataPaginate("name",10,$request->keyword);
        }
        return $this->autoResponse($data);
    }

    public function storeParent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer',
            'uuid_from' => 'required|string',
            'issue_date' => 'required|date',
            'due_date' => 'required|date',
            'reference' => 'required|string',
            'action_save' => 'required|integer|between:0,2',

            'item_id' => 'required|array|min:1',
            'desc' => 'required|array|min:1',
            'qty' => 'required|array|min:1',
            'unit_price' => 'required|array|min:1',
            'coa_id' => 'required|array|min:1',
            'paket_tracking_uuid' => 'nullable|array',
            'divisi_travel_tracking_uuid' => 'nullable|array',
            'id_detail' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }

        // Gunakan merge agar field ini terbaca dengan baik saat request->except() atau validasi lanjutan
        $request->merge([
            'status' => $request->action_save, // 0->draft, 1/2->approve
            'reference' => strtolower($request->reference)
        ]);

        DB::beginTransaction();
        try {
            // 1. Save Parent
            $saveP = $this->repo->CreateOrUpdate(
                $request->except(['coa_id', 'desc', 'qty', 'unit_price', 'nama_paket', 'divisi', 'id_detail', 'action_save']),
                $request->id
            );

            // 2. Hapus Detail yang Dibuang (Lakukan DI LUAR LOOP)
            // Pastikan kita hanya mengecek jika ini adalah proses Update (id tidak null)
            if ($saveP->id) {
                $allDetailIds = $this->repo_detail->whereData(['parent_inv_id' => $saveP->id])->pluck('id')->toArray();

                // Hindari error jika $request->id_detail kosong/null
                $providedDetailIds = $request->id_detail ? array_filter($request->id_detail) : [];
                $deleted_array = array_diff($allDetailIds, $providedDetailIds);

                if (!empty($deleted_array)) {
                    // Asumsi wherenDataIn adalah fungsi custom repository Anda (mirip whereIn eloquent)
                    $deletedUuids = $this->repo_detail->wherenDataIn('id', $deleted_array)->pluck('uuid_detail_inv')->toArray();

                    // B. Hapus data di tabel all_trans berdasarkan uuid_detail tersebut
                    if (!empty($deletedUuids)) {
                        // Asumsi repo_all_trans juga memiliki fungsi wherenDataIn
                        $this->repo_all_trans->wherenDataIn('uuid_detail', $deletedUuids)->delete();
                    }
                    $this->repo_detail->wherenDataIn('id', $deleted_array)->delete();
                }
            }

            // 3. Save Details (Create / Update)
            foreach ($request->coa_id as $key => $accountId) {
                $detailId = $request->id_detail[$key] ?? null;

                $detailData = [
                    'invoice_number' => $saveP->invoice_number,
                    'coa_id' => $accountId,
                    'desc' => $request->desc[$key] ?? null,
                    'qty' => $request->qty[$key] ?? 0,
                    'unit_price' => $request->unit_price[$key] ?? 0,
                    'total_amount_each_row' => ($request->qty[$key] ?? 0) * ($request->unit_price[$key] ?? 0),
                    'paket_tracking_uuid' => $request->paket_tracking_uuid[$key] ?? null,
                    'divisi_travel_tracking_uuid' => $request->divisi_travel_tracking_uuid[$key] ?? null,
                ];

                // FIX: Hanya generate UUID_DETAIL jika ini adalah baris baru (bukan edit)
                if (empty($detailId)) {
                    $detailData['uuid_detail_inv'] = $this->service_global->generateUniqueString();
                }

                // Create atau Update Detail
                $save_d = $this->repo_detail->CreateOrUpdate($detailData, $detailId);

                // 4. Manajemen Transaksi (Jika approve / action_save != 0)
                if ($request->action_save != 0) {

                    $cek_create_trans = $this->repo_all_trans->whereData([
                        'reference' => $request->reference, // Sudah di-strtolower via merge
                        'uuid_coa' => $accountId,
                        'uuid_detail' => $save_d->uuid_detail_inv
                    ])->first();

                    if ($cek_create_trans) {
                        // FIX: Jika transaksi sudah ada, update nominal menggunakan data terbaru dari $save_d
                        $cek_create_trans->is_speend = false;
                        $cek_create_trans->nominal = $save_d->amount;
                        $cek_create_trans->save();
                    } else {
                        // FIX: uuid_detail harus disamakan dengan punya tabel detail ($save_d->uuid_detail), bukan di-generate ulang
                        $data_trans_create = [
                            'date_transaction' => $request->date_req,
                            'uuid_coa' => $accountId,
                            'reference' => $request->reference,
                            'is_speend' => false,
                            'nominal' => $save_d->total_amount_each_row,
                            'created_by' => $request->user_login->id, // Pastikan user_login dilampirkan via middleware
                            'uuid_detail' => $save_d->uuid_detail_inv
                        ];
                        $this->repo_all_trans->CreateOrUpdate($data_trans_create, null);
                    }
                }
            }

            // 5. Update Total Keseluruhan Parent
            $sumD = $this->repo_detail->sumDataWhereDinamis(['parent_inv_id' => $saveP->id], 'total_amount_each_row');
            $this->repo->CreateOrUpdate(['invoice_amount' => $sumD], $saveP->id);

            $this->service_global->saveLogHistory(
                $request->user_login->id,
                $request->user_login->name . ' save transaksi invoice ' . $saveP->contact_name,
                $request->userAgent(),
                $request->ip()
            );

            DB::commit();
            return $this->autoResponse($saveP);

        } catch (\Throwable $th) {
            DB::rollBack();
            // Memunculkan pesan error dengan lengkap sangat membantu saat debugging di network tab inspect element
            return $this->error($th->getMessage() . ' at line ' . $th->getLine(), 500);
        }
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



    public function cekPaymentAda($paymentId)
    {
        $responsePayment = Http::withHeaders($this->getHeaders())->get($this->xeroBaseUrl . '/Payments/' . $paymentId);
        $res = $responsePayment->json('Payments.0');
        if ($res != null) {
            return $res;
        } else {
            return NULL;
        }
    }







}
