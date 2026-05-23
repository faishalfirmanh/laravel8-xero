<?php

namespace App\Http\Controllers\Transaction\Bank;

use App\Http\Controllers\Controller;
use App\Http\Repository\Expenses\PODBillRepository;
use App\Http\Repository\Expenses\POPBillRepository;
use App\Http\Repository\MasterData\BankXeroRepo;
use App\Http\Repository\Transaction\TransBankDRepository;
use App\Http\Repository\Transaction\TransBankPRepository;
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


class BankSpendReceiveController extends Controller
{
    //
    protected $repo, $repo_detail, $service_global, $repo_all_trans, $repo_trans_all_bank;
    protected $repo_bank_p_trans, $repo_bank_d_trans;
    use ConfigRefreshXero;
    use ApiResponse;
    public function __construct(
        BankXeroRepo $repo,
        PODBillRepository $repo_detail,
        GlobalService $service_global,
        TransCoaRepo $repo_all_trans,
        TransBankRepo $repo_trans_all_bank,
        TransBankPRepository $repo_bank_p_trans,
        TransBankDRepository $repo_bank_d_trans
    ) {
        $this->repo = $repo;
        $this->repo_detail = $repo_detail;
        $this->service_global = $service_global;
        $this->repo_all_trans = $repo_all_trans;
        $this->repo_trans_all_bank = $repo_trans_all_bank;

        $this->repo_bank_p_trans = $repo_bank_p_trans;
        $this->repo_bank_d_trans = $repo_bank_d_trans;
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
            $data = $this->repo->searchData($where, $request->limit, $request->page, 'name', strtoupper($request->keyword));
        } else {
            $data = $this->repo->getAllDataWithDefault($where, $request->limit, $request->page, 'name', 'ASC');//getDataPaginate("name",10,$request->keyword);
        }
        return $this->autoResponse($data);
    }


    public function getAllPaginateDetail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'required|integer',
            'keyword' => 'nullable|string',
            'kolom_name' => 'required|string',
            'limit' => 'required|integer',
            'bank_id_xero' => 'required|integer|exists:bank_xeros,id'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }
        $where = ['uuid_bank' => $request->bank_id_xero];
        if ($request->keyword != null) {
            $data = $this->repo_trans_all_bank->searchData($where, $request->limit, $request->page, 'reference_detail', strtoupper($request->keyword), ['getPbill', 'getPBank']);
        } else {
            $data = $this->repo_trans_all_bank->getAllDataWithDefault($where, $request->limit, $request->page, 'date_transaction', 'DESC', ['getPbill', 'getPBank']);//getDataPaginate("name",10,$request->keyword);
        }
        return $this->autoResponse($data);
    }



    public function storeParent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer',
            'uuid_to' => 'required|string',
            'date_h' => 'required|date',
            'reference' => 'required|string',
            'ammounts_are' => 'required|integer|between:0,2',
            'is_spend' => 'required|boolean',
            'bank_id_xero' => 'required|integer|exists:bank_xeros,id',

            'desc' => 'required|array|min:1',
            'qty' => 'required|array|min:1',
            'unit_price' => 'required|array|min:1',
            'paket_tracking_uuid' => 'nullable|array',
            'divisi_travel_tracking_uuid' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }

        // Gunakan merge agar field ini terbaca dengan baik saat request->except() atau validasi lanjutan
        $request->merge([
            'reference' => strtolower($request->reference),
            'created_by' => $request->user_login->id,
        ]);

        DB::beginTransaction();
        try {
            // 1. Save Parent
            $saveP = $this->repo_bank_p_trans->CreateOrUpdate(
                $request->except(['account_id', 'desc', 'qty', 'unit_price', 'tax_rate', 'nama_paket', 'divisi']),
                $request->id
            );

            // 2. Hapus Detail yang Dibuang (Lakukan DI LUAR LOOP)
            // Pastikan kita hanya mengecek jika ini adalah proses Update (id tidak null)
            if ($saveP->id) {
                $allDetailIds = $this->repo_bank_d_trans->whereData(['trans_bank_parent_id' => $saveP->id])->pluck('id')->toArray();

                // Hindari error jika $request->id_detail kosong/null
                $providedDetailIds = $request->id_detail ? array_filter($request->id_detail) : [];
                $deleted_array = array_diff($allDetailIds, $providedDetailIds);

                if (!empty($deleted_array)) {
                    // Asumsi wherenDataIn adalah fungsi custom repository Anda (mirip whereIn eloquent)
                    $deletedUuids = $this->repo_bank_d_trans->wherenDataIn('id', $deleted_array)->pluck('uuid_detail')->toArray();

                    // B. Hapus data di tabel all_trans berdasarkan uuid_detail tersebut
                    if (!empty($deletedUuids)) {
                        // Asumsi repo_all_trans juga memiliki fungsi wherenDataIn
                        $this->repo_all_trans->wherenDataIn('uuid_detail', $deletedUuids)->delete();
                    }
                    $this->repo_bank_d_trans->wherenDataIn('id', $deleted_array)->delete();
                }
            }


            $this->service_global->saveLogHistory(
                $request->user_login->id,
                $request->user_login->name . ' save transaksi bank ' . $saveP->name_contact,
                $request->userAgent(),
                $request->ip()
            );

            // 3. Save Details (Create / Update)
            foreach ($request->account_id as $key => $accountId) {
                $detailId = $request->id_detail[$key] ?? null;

                $detailData = [
                    'trans_bank_parent_id' => $saveP->id,
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
                    $detailData['uuid_detail_trans_bank'] = $this->service_global->generateUniqueString();
                }

                // Create atau Update Detail
                $save_d = $this->repo_bank_d_trans->CreateOrUpdate($detailData, $detailId);

                // 4. Manajemen Transaksi (Jika approve / action_save != 0)
                //if ($request->action_save != 0) {

                //cek coa
                $cek_create_trans = $this->repo_all_trans->whereData([
                    'reference' => $request->reference,
                    'uuid_coa' => $accountId,
                    'uuid_detail' => $save_d->uuid_detail_trans_bank
                ])->first();


                if ($cek_create_trans) {
                    // FIX: Jika transaksi sudah ada, update nominal menggunakan data terbaru dari $save_d
                    $cek_create_trans->is_speend = true;
                    $cek_create_trans->nominal = $save_d->amount;
                    $cek_create_trans->save();
                } else {
                    // FIX: uuid_detail harus disamakan dengan punya tabel detail ($save_d->uuid_detail), bukan di-generate ulang
                    $data_trans_create = [
                        'date_transaction' => $request->date_h,
                        'uuid_coa' => $accountId,
                        'reference' => $request->reference,
                        'is_speend' => true,
                        'nominal' => $save_d->amount,
                        'created_by' => $request->user_login->id, // Pastikan user_login dilampirkan via middleware
                        'uuid_detail' => $save_d->uuid_detail_trans_bank
                    ];
                    $this->repo_all_trans->CreateOrUpdate($data_trans_create, null);
                }
                //}
            }

            // 5. Update Total Keseluruhan Parent
            $sumD = $this->repo_bank_d_trans->sumDataWhereDinamis(['trans_bank_parent_id' => $saveP->id], 'amount');
            $this->repo_bank_p_trans->CreateOrUpdate(['amount' => $sumD], $saveP->id);

            //dd($request->is_spend);
            $send_money_bank = [
                'date_transaction' => $request->date_h,
                'uuid_bank' => $request->bank_id_xero,
                'reference_detail' => $request->reference,
                'id_parent_bank' => $saveP->id,
                'created_by' => $request->user_login->id,
            ];

            if ($request->is_spend == 1) {
                $send_money_bank['nominal_spend'] = $sumD;
            } else {
                $send_money_bank['nominal_receive'] = $sumD;
            }


            $this->repo_trans_all_bank->CreateOrUpdate($send_money_bank, null);

            DB::commit();
            return $this->autoResponse($saveP);

        } catch (\Throwable $th) {
            DB::rollBack();
            // Memunculkan pesan error dengan lengkap sangat membantu saat debugging di network tab inspect element
            return $this->error($th->getMessage() . ' at line ' . $th->getLine(), 500);
        }
    }

    //used
    public function getDetailTransBank(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:transaction_bank_trans_p_s,id'
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors());
        }
        // dd(222);
        $data = $this->repo_bank_p_trans->WhereDataWith(['getDetail'], ['id' => $request->id])->first();
        return $this->autoResponse($data);
    }





}
