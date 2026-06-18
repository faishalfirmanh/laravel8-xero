<?php

namespace App\Http\Controllers\Transaction\Expenses;

use App\Http\Controllers\Controller;
use App\Http\Repository\Expenses\PODBillRepository;
use App\Http\Repository\Expenses\POPBillRepository;
use App\Http\Repository\Transaction\TransBankRepo;
use App\Http\Repository\Transaction\TransCoaRepo;
use Illuminate\Http\Request;

use App\Http\Repository\Expenses\DPackageExpensesRepository;
use App\Http\Repository\MasterData\Finance\ItemPaketAllXeroRepo;
use App\Http\Repository\MasterData\Finance\InvoiceAllXeroRepo;
use App\Http\Repository\Expenses\DInvPackageExpensesRepository;
use App\Http\Repository\Expenses\PackageExpensesRepository;
use Validator;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;
use App\Services\GlobalService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use App\ConfigRefreshXero;
use App\Models\Revenue\Hotel\DetailInvoicesHotel;
use App\Models\Revenue\Hotel\InvoicesHotel;
use App\Models\Config\ConfigCurrency;
use Barryvdh\DomPDF\Facade\Pdf;


class BillXeroController extends Controller
{
    //
    protected $repo, $repo_detail, $service_global, $repo_all_trans, $repo_trans_bill;
    use ConfigRefreshXero;
    use ApiResponse;
    public function __construct(
        POPBillRepository $repo,
        PODBillRepository $repo_detail,
        GlobalService $service_global,
        TransCoaRepo $repo_all_trans,
        TransBankRepo $repo_trans_bill
    ) {
        $this->repo = $repo;
        $this->repo_detail = $repo_detail;
        $this->service_global = $service_global;
        $this->repo_all_trans = $repo_all_trans;
        $this->repo_trans_bill = $repo_trans_bill;
    }

    //used
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
            $data = $this->repo->searchData($where, $request->limit, $request->page, 'uuid_from', strtoupper($request->keyword));
        } else {
            $data = $this->repo->getAllDataWithDefault($where, $request->limit, $request->page, 'id', 'DESC');//getDataPaginate("name",10,$request->keyword);
        }
        return $this->autoResponse($data);
    }

    public function storePayment(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer',
            'uuid_bank' => 'required|integer|exists:bank_xeros,id',
            'nominal_spend' => 'required|integer',
            'reference_detail' => 'required|string',
            'date_transaction' => 'required|date',
            'id_parent_bill' => 'required|integer|exists:p_bills,id'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }
        $findData = $this->repo->whereData(['id' => $request->id_parent_bill])->first();

        $sisaTagihan = $findData->nominal_due - $findData->nominal_paid;

        if ($request->nominal_spend > $findData->nominal_due) {
            return $this->error("Nominal melebihi total tagihan (due: {$findData->nominal_due})", 400);
        }

        if ($request->nominal_spend > $sisaTagihan) {
            return $this->error("Nominal melebihi sisa tagihan yang belum dibayar (sisa: {$sisaTagihan})", 400);
        }

        $request->merge([
            'created_by' => $request->user_login->id,
            'nominal_transfer' => 0,
            'nominal_receive' => 0
        ]);

        $nominal_paid_final = $findData->nominal_paid + $request->nominal_spend;
        $nominal_due_final = $findData->total - $nominal_paid_final;

        $param_bill_save = ['nominal_paid' => $nominal_paid_final, 'nominal_due' => $nominal_due_final];
        $this->repo->CreateOrUpdate($param_bill_save, $request->id_parent_bill);
        $saveP = $this->repo_trans_bill->CreateOrUpdate($request->all(), null);
        return $this->autoResponse($saveP);

    }

    public function storeParent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer',
            'uuid_from' => 'required|string',
            'date_req' => 'required|date',
            'due_date' => 'required|date',
            'reference' => 'required|string',
            'currency' => 'required|string',
            'account_id' => 'required|array|min:1',
            'action_save' => 'required|integer|between:0,2',

            'desc' => 'required|array|min:1',
            'qty' => 'required|array|min:1',
            'unit_price' => 'required|array|min:1',
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
                $request->except(['account_id', 'desc', 'qty', 'unit_price', 'tax_rate', 'nama_paket', 'divisi', 'id_detail', 'action_save']),
                $request->id
            );

            // 2. Hapus Detail yang Dibuang (Lakukan DI LUAR LOOP)
            // Pastikan kita hanya mengecek jika ini adalah proses Update (id tidak null)
            if ($saveP->id) {
                $allDetailIds = $this->repo_detail->whereData(['bills_parent_id' => $saveP->id])->pluck('id')->toArray();

                // Hindari error jika $request->id_detail kosong/null
                $providedDetailIds = $request->id_detail ? array_filter($request->id_detail) : [];
                $deleted_array = array_diff($allDetailIds, $providedDetailIds);

                if (!empty($deleted_array)) {
                    // Asumsi wherenDataIn adalah fungsi custom repository Anda (mirip whereIn eloquent)
                    $deletedUuids = $this->repo_detail->wherenDataIn('id', $deleted_array)->pluck('uuid_detail')->toArray();

                    // B. Hapus data di tabel all_trans berdasarkan uuid_detail tersebut
                    if (!empty($deletedUuids)) {
                        // Asumsi repo_all_trans juga memiliki fungsi wherenDataIn
                        $this->repo_all_trans->wherenDataIn('uuid_detail', $deletedUuids)->delete();
                    }
                    $this->repo_detail->wherenDataIn('id', $deleted_array)->delete();
                }
            }


            $this->service_global->saveLogHistory(
                $request->user_login->id,
                $request->user_login->name . ' save transaksi bills ' . $saveP->name_contact,
                $request->userAgent(),
                $request->ip()
            );

            // 3. Save Details (Create / Update)
            foreach ($request->account_id as $key => $accountId) {
                $detailId = $request->id_detail[$key] ?? null;

                $detailData = [
                    'bills_parent_id' => $saveP->id,
                    'account_id_coa' => $accountId,
                    'desc' => $request->desc[$key] ?? null,
                    'qty' => $request->qty[$key] ?? 0,
                    'unit_price' => $request->unit_price[$key] ?? 0,
                    'amount' => ($request->qty[$key] ?? 0) * ($request->unit_price[$key] ?? 0),
                    'paket_tracking_uuid' => $request->paket_tracking_uuid[$key] ?? null,
                    'divisi_travel_tracking_uuid' => $request->divisi_travel_tracking_uuid[$key] ?? null,
                ];

                // FIX: Hanya generate UUID_DETAIL jika ini adalah baris baru (bukan edit)
                if (empty($detailId)) {
                    $detailData['uuid_detail'] = $this->service_global->generateUniqueString();
                }

                // Create atau Update Detail
                $save_d = $this->repo_detail->CreateOrUpdate($detailData, $detailId);

                // 4. Manajemen Transaksi (Jika approve / action_save != 0)
                if ($request->action_save != 0) {

                    $cek_create_trans = $this->repo_all_trans->whereData([
                        'reference' => $request->reference, // Sudah di-strtolower via merge
                        'uuid_coa' => $accountId,
                        'uuid_detail' => $save_d->uuid_detail
                    ])->first();

                    if ($cek_create_trans) {
                        // FIX: Jika transaksi sudah ada, update nominal menggunakan data terbaru dari $save_d
                        $cek_create_trans->is_speend = true;
                        $cek_create_trans->nominal = $save_d->amount;
                        $cek_create_trans->save();
                    } else {
                        // FIX: uuid_detail harus disamakan dengan punya tabel detail ($save_d->uuid_detail), bukan di-generate ulang
                        $data_trans_create = [
                            'date_transaction' => $request->date_req,
                            'uuid_coa' => $accountId,
                            'reference' => $request->reference,
                            'is_speend' => true,
                            'nominal' => abs((int) $save_d->amount),//auto positif
                            'created_by' => $request->user_login->id, // Pastikan user_login dilampirkan via middleware
                            'uuid_detail' => $save_d->uuid_detail
                        ];
                        $this->repo_all_trans->CreateOrUpdate($data_trans_create, null);
                    }
                }
            }

            // 5. Update Total Keseluruhan Parent
            $sumD = $this->repo_detail->sumDataWhereDinamis(['bills_parent_id' => $saveP->id], 'amount');
            $this->repo->CreateOrUpdate(['total' => $sumD, 'nominal_due' => $sumD], $saveP->id);

            DB::commit();
            return $this->autoResponse($saveP);

        } catch (\Throwable $th) {
            DB::rollBack();
            // Memunculkan pesan error dengan lengkap sangat membantu saat debugging di network tab inspect element
            return $this->error($th->getMessage() . ' at line ' . $th->getLine(), 500);
        }
    }

    //used
    public function detailBill(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:p_bills,id'
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors());
        }
        // dd(222);
        $data = $this->repo->WhereDataWith(['getDetail', 'getContactFrom', 'getPayment'], ['id' => $request->id])->first();
        return $this->autoResponse($data);
    }





}
