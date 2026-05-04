<?php

namespace App\Http\Controllers\Transaction\Expenses;

use App\Http\Controllers\Controller;
use App\Http\Repository\Expenses\PODBillRepository;
use App\Http\Repository\Expenses\POPBillRepository;
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
    protected $repo, $repo_detail, $service_global;
    use ConfigRefreshXero;
    use ApiResponse;
    public function __construct(
        POPBillRepository $repo,
        PODBillRepository $repo_detail,
        GlobalService $service_global
    ) {
        $this->repo = $repo;
        $this->repo_detail = $repo_detail;
        $this->service_global = $service_global;
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
            $data = $this->repo->getAllDataWithDefault($where, $request->limit, $request->page, 'uuid_from', 'ASC');//getDataPaginate("name",10,$request->keyword);
        }
        return $this->autoResponse($data);
    }


    //old
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

            'desc' => 'required|array|min:1',
            'qty' => 'required|array|min:1',
            'unit_price' => 'required|array|min:1',
            'paket_tracking_uuid' => 'nullable|array|min:0',
            'divisi_travel_tracking_uuid' => 'nullable|array|min:0',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }

        $request['status'] = 0;
        $request['reference'] = strtolower($request->reference);

        DB::beginTransaction();
        try {
            $saveP = $this->repo->CreateOrUpdate($request->except(['account_id', 'desc', 'qty', 'unit_price', 'tax_rate', 'nama_paket', 'divisi']), $request->id);

            // Save Details
            foreach ($request->account_id as $key => $accountId) {
                // Build the specific detail array using the current $key index
                $detailData = [
                    'bills_parent_id' => $saveP->id,
                    'account_id_coa' => $accountId,
                    'desc' => $request->desc[$key] ?? null,
                    'qty' => $request->qty[$key] ?? 0,
                    'unit_price' => $request->unit_price[$key] ?? 0,
                    'amount' => ($request->qty[$key] ?? 0) * ($request->unit_price[$key] ?? 0),
                    'paket_tracking_uuid' => $request->paket_tracking_uuid[$key] ?? NULL,
                    'divisi_travel_tracking_uuid' => $request->divisi_travel_tracking_uuid[$key] ?? NULL,
                ];
                // Assuming CreateOrUpdate accepts the array of data to insert
                $this->repo_detail->CreateOrUpdate($detailData, null);
            }
            $sumD = $this->repo_detail->sumDataWhereDinamis(['bills_parent_id' => $saveP->id], 'amount');
            $this->repo->CreateOrUpdate(['total' => $sumD], $saveP->id);
            DB::commit();
            return $this->autoResponse($saveP);
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->error($th->getMessage(), 500);
        }

    }

    //used
    public function storeDetail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'package_expenses_id' => 'required|array',
            'package_expenses_id.*' => 'exists:package_expenses_xeros,id',
            //
            'pengeluaran_id' => 'required|array',
            'pengeluaran_id.*' => 'exists:master_pengeluaran_pakets,id',
            //
            'is_idr' => 'required|array',
            'is_idr.*' => 'boolean',
            'detail_id' => 'nullable'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }

        $totalRow = count($request->package_expenses_id);
        $aa = 0;
        $id_parent = [];
        for ($i = 0; $i < $totalRow; $i++) {
            // dd( $request->package_expenses_id[$i]);
            $convert_sar = $request->is_idr[$i] == 0 ?
                $request->nominal_sar[$i] * $request->nominal_currency[$i]
                : $request->nominal_idr[$i];
            $dataToSave = [
                'package_expenses_id' => $request->package_expenses_id[$i],
                'pengeluaran_id' => $request->pengeluaran_id[$i],
                'nominal_currency' => $request->nominal_currency[$i],
                'is_idr' => $request->is_idr[$i],
                'nominal_idr' => $convert_sar,
                'nominal_sar' => $request->nominal_sar[$i] ?? 0,
                'combine_id_random' => $this->service_global->generateUniqueRandomString()
            ];
            $id_parent[] = $request->package_expenses_id[$i];
            if (isset($request->detail_id[$i])) {
                $this->repo_detail->CreateOrUpdate($dataToSave, $request->detail_id[$i]);
            } else {
                $this->repo_detail->CreateOrUpdate($dataToSave, null);
            }
            $aa++;
        }
        $sum_detail_pengeluaran = $this->repo_detail->sumDataWhereDinamis(['package_expenses_id' => $id_parent[0]], 'nominal_idr');
        $data_parent = $this->repo->whereData(['id' => $id_parent[0]])->first();
        $nominal_penjualan = $data_parent->nominal_sales;
        $laba_bersih = $nominal_penjualan - $sum_detail_pengeluaran;
        $updated = $this->repo->CreateOrUpdate(['nominal_purchase' => $sum_detail_pengeluaran, 'nominal_profit' => $laba_bersih], $id_parent[0]);
        // return response()->json(['msg' => 'success','parent_id'=>2,'tersimpan'=>$aa], 200);
        return $this->autoResponse($updated);
    }

    public function getById(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:package_expenses_xeros,id'
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors());
        }
        // dd(222);
        $data = $this->repo->WhereDataWith(['details', 'detailsLocalInvoice'], ['id' => $request->id])->first();
        return $this->autoResponse($data);
    }


    public function deleteDetail(Request $request)
    {
        // 1. Validasi
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:d_package_expenses_xeros,id'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }

        //DB::beginTransaction(); // Mulai Transaksi
        try {
            $detail = $this->repo_detail->whereData(['id' => $request->id])->first();
            $parentId = $detail->package_expenses_id;

            $this->repo_detail->deleteWithIdDinamis('id', $request->id);
            $new_sum_purchase = $this->repo_detail->sumDataWhereDinamis(
                ['package_expenses_id' => $parentId],
                'nominal_idr'
            );

            $data_parent = $this->repo->whereData(['id' => $parentId])->first();
            $new_profit = $data_parent->nominal_sales - $new_sum_purchase;

            $update_parent = $this->repo->CreateOrUpdate([
                'nominal_purchase' => $new_sum_purchase,
                'nominal_profit' => $new_profit
            ], $parentId);

            // DB::commit();
            $data_after_saved = $this->repo->WhereDataWith(['details', 'detailsLocalInvoice'], ['id' => $parentId])->first();
            return $this->autoResponse(['success' => true, 'data_after_saved' => $data_after_saved], "Berhasil dihapus");

        } catch (\Exception $e) {
            //DB::rollBack();
            return $this->error("Gagal menghapus: " . $e->getMessage());
        }
    }

    public function deletedExpenses(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:package_expenses_xeros,id'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }
        // dd($request->id);
        $detail = $this->repo_detail->deleteWithIdDinamisMultiRow('package_expenses_id', $request->id);
        $inv_detail = $this->repo_d_invoice->deleteWithIdDinamisMultiRow('package_expenses_id', $request->id);
        $parnet = $this->repo->deleteWithIdDinamisMultiRow('id', $request->id);
        return $this->autoResponse($parnet);
    }








}
