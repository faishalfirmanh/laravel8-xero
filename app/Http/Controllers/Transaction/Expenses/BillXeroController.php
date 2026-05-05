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
            'id_detail' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }

        $request['status'] = 0;
        $request['reference'] = strtolower($request->reference);

        DB::beginTransaction();
        try {
            $saveP = $this->repo->CreateOrUpdate($request->except(['account_id', 'desc', 'qty', 'unit_price', 'tax_rate', 'nama_paket', 'divisi']), $request->id);

            $all = $this->repo_detail->whereData(['bills_parent_id' => $saveP->id])->pluck('id')->toArray();
            $except = $this->repo_detail->wherenDataIn('id', $request->id_detail)->pluck('id')->toArray();
            $deleted_array = array_diff($all, $except);




            // Save Details
            foreach ($request->account_id as $key => $accountId) {
                // Build the specific detail array using the current $key index
                // dd($request->id_detail[$key]);
                $this->repo_detail->wherenDataIn('id', $deleted_array)->delete();
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
                $this->repo_detail->CreateOrUpdate($detailData, $request->id_detail[$key] ?? NULL);
                if ($request->id_detail[$key]) {
                    $cariD = $this->repo_detail->whereData(['bills_parent_id' => $saveP->id, 'id' => $request->id_detail[$key]])->first();

                }
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
    public function detailBill(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:p_bills,id'
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors());
        }
        // dd(222);
        $data = $this->repo->WhereDataWith(['getDetail', 'getContactFrom'], ['id' => $request->id])->first();
        return $this->autoResponse($data);
    }





}
