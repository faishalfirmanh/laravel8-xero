<?php

namespace App\Http\Controllers\Transaction\Expenses;

use App\Http\Controllers\Controller;
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


class ExpensesPackageApiController extends Controller
{
    //
    protected $repo, $repo_detail, $service_global, $repo_invoice, $repo_item, $repo_d_invoice;
    use ConfigRefreshXero;
    use ApiResponse;
    public function __construct(
        PackageExpensesRepository $repo,
        DPackageExpensesRepository $repo_detail,
        GlobalService $service_global,
        InvoiceAllXeroRepo $repo_invoice,
        ItemPaketAllXeroRepo $repo_item,
        DInvPackageExpensesRepository $repo_d_invoice
    ) {
        $this->repo = $repo;
        $this->repo_detail = $repo_detail;
        $this->service_global = $service_global;
        $this->repo_invoice = $repo_invoice;
        $this->repo_item = $repo_item;
        $this->repo_d_invoice = $repo_d_invoice;
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
            $data = $this->repo->searchData($where, $request->limit, $request->page, 'name_paket', strtoupper($request->keyword));
        } else {
            $data = $this->repo->getAllDataWithDefault($where, $request->limit, $request->page, 'name_paket', 'ASC');//getDataPaginate("name",10,$request->keyword);
        }
        return $this->autoResponse($data);
    }

    //used
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'=>'nullable|integer',
            'invoice_ids'=>'required|array',
            'uuid_paket_item' => 'required|string',
            // 'code_paket' => 'required|string',
            // 'name_paket' => 'required|string',
            // 'nominal_purchase' => 'required|numeric',
            // 'nominal_sales' => 'required|numeric',
            // 'nominal_profit' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }

       $sum_all_uang_masuk = $this->repo_invoice->sumDataWhereIn($request->invoice_ids,'invoice_total');
       //ambil dari xero sub total, kalau paid dari AmountPaid
       $get_paket =$this->repo_item->whereData(['uuid_proudct_and_service'=> $request->uuid_paket_item])->first();

       $request['name_paket'] = $get_paket->nama_paket;
       $request['code_paket'] = $get_paket->code;
       $request['nominal_sales'] = $sum_all_uang_masuk;//penjualan
        if ($validator->fails()) {
            return $this->error($validator->errors());
        }
        $saved_i = $this->repo->CreateOrUpdate($request->all(), $request->id);
       // $saved_details = $this->repo_detail->CreateOrUpdate($request->all(), $request->id);

        $get_invoice_uuid = $this->repo_invoice->getWhereDataIn($request->invoice_ids);
        $id_invoice_local_xero = [];
        foreach ($get_invoice_uuid as $key => $value) {

            $saved_data = [
                'package_expenses_id'=>$saved_i->id,
                'invoices_xero_id'=>$value->id,
                'amount_invoice'=>$value->invoice_total
            ];
            $this->repo_d_invoice->CreateOrUpdate($saved_data,null);
        }

        return $this->autoResponse($saved_i);
    }

    //used
     public function storeDetail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'package_expenses_id'=>'required|array',
            'package_expenses_id.*' => 'exists:package_expenses_xeros,id',
            //
            'pengeluaran_id'=>'required|array',
            'pengeluaran_id.*'      => 'exists:master_pengeluaran_pakets,id',
            //
            'is_idr' => 'required|array',
            'is_idr.*'              => 'boolean',
            'detail_id'=> 'nullable'
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
                'pengeluaran_id'      => $request->pengeluaran_id[$i],
                'nominal_currency'=>$request->nominal_currency[$i],
                'is_idr'              => $request->is_idr[$i],
                'nominal_idr'         => $convert_sar,
                'nominal_sar'         => $request->nominal_sar[$i] ?? 0,
                'combine_id_random'=> $this->service_global->generateUniqueRandomString()
            ];
            $id_parent[] = $request->package_expenses_id[$i];
            if(isset( $request->detail_id[$i])){
                $this->repo_detail->CreateOrUpdate($dataToSave, $request->detail_id[$i]);
            }else{
                $this->repo_detail->CreateOrUpdate($dataToSave,null);
            }
            $aa++;
        }
        $sum_detail_pengeluaran = $this->repo_detail->sumDataWhereDinamis(['package_expenses_id'=>$id_parent[0]],'nominal_idr');
        $data_parent = $this->repo->whereData(['id'=>$id_parent[0]])->first();
        $nominal_penjualan = $data_parent->nominal_sales;
        $laba_bersih = $nominal_penjualan - $sum_detail_pengeluaran;
        $updated = $this->repo->CreateOrUpdate(['nominal_purchase'=>$sum_detail_pengeluaran,'nominal_profit'=>$laba_bersih],$id_parent[0]);
       // return response()->json(['msg' => 'success','parent_id'=>2,'tersimpan'=>$aa], 200);
         return $this->autoResponse($updated);
    }

    public function getById(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'=>'required|exists:package_expenses_xeros,id'
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors());
        }
       // dd(222);
        $data = $this->repo->WhereDataWith(['details','detailsLocalInvoice'], ['id' => $request->id])->first();
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
                'nominal_profit'   => $new_profit
            ], $parentId);

           // DB::commit();
            return $this->autoResponse(true, "Berhasil dihapus");

        } catch (\Exception $e) {
            //DB::rollBack();
            return $this->error("Gagal menghapus: " . $e->getMessage());
        }
    }

    public function deletedExpenses(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'=>'required|exists:package_expenses_xeros,id'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }
        // dd($request->id);
        $detail = $this->repo_detail->deleteWithIdDinamisMultiRow('package_expenses_id',$request->id);
        $inv_detail =   $this->repo_d_invoice->deleteWithIdDinamisMultiRow('package_expenses_id',$request->id);
        $parnet = $this->repo->deleteWithIdDinamisMultiRow('id',$request->id);
        return $this->autoResponse($parnet);
    }








}
