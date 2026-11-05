<?php

namespace App\Http\Controllers\Transaction\Expenses;

use App\Http\Controllers\Controller;
use App\Http\Repository\Expenses\PODBillRepository;
use App\Http\Repository\Expenses\POPBillRepository;
use App\Http\Repository\LogHistoryRepository;
use App\Http\Repository\MasterData\CoaRepo;
use App\Http\Repository\MasterData\Finance\InvoiceAllXeroRepo;
use App\Http\Repository\MasterData\Finance\ItemPaketAllXeroRepo;
use App\Http\Repository\Transaction\FeeOverpayRepo;
use App\Http\Repository\Transaction\OverPayRepo;
use App\Http\Repository\Transaction\SummaryBankRepo;
use App\Http\Repository\Transaction\TransBankRepo;
use App\Http\Repository\Transaction\TransCoaRepo;
use App\Models\MasterData\MasterCurrency;
use App\Models\Transaction\SummaryNominalBank;
use Illuminate\Http\Request;


use Validator;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;
use App\Services\GlobalService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use App\ConfigRefreshXero;
use Illuminate\Support\Facades\File;

use Intervention\Image\Facades\Image;
use Cache;

class OverpayController extends Controller
{
    //
    protected $repo, $repo_overpay, $repo_coa, $repo_trans_bank, $repo_trans_coa, $repo_invoice, $repo_master_item, $globa_service, $repo_summary_bank;
    use ConfigRefreshXero;
    use ApiResponse;
    public function __construct(
        FeeOverpayRepo $repo,
        OverPayRepo $repo_overpay,
        CoaRepo $repo_coa,
        TransBankRepo $repo_trans_bank,
        TransCoaRepo $repo_trans_coa,
        InvoiceAllXeroRepo $repo_invoice,
        ItemPaketAllXeroRepo $repo_master_item,
        GlobalService $globa_service,
        SummaryBankRepo $repo_summary_bank
    ) {
        $this->repo = $repo;
        $this->repo_overpay = $repo_overpay;
        $this->repo_coa = $repo_coa;
        $this->repo_trans_bank = $repo_trans_bank;
        $this->repo_trans_coa = $repo_trans_coa;
        $this->repo_invoice = $repo_invoice;
        $this->repo_master_item = $repo_master_item;
        $this->globa_service = $globa_service;
        $this->repo_summary_bank = $repo_summary_bank;
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
        $searchColumns = ['nominal_overpayment'];
        $where = [];
        if ($request->keyword != null) {
            $data = $this->repo_overpay->searchData($where, $request->limit, $request->page, 'nominal_overpayment', strtoupper($request->keyword));
        } else {
            $data = $this->repo_overpay->getAllDataWithDefault($where, $request->limit, $request->page, 'nominal_overpayment', 'ASC');//getDataPaginate("name",10,$request->keyword);
        }
        return $this->autoResponse($data);
    }


    public function updateNOminal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:overpayments,id',
            'nominal_overpayment' => 'required|numeric'
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors(), 500);
        }
        $data = $this->repo_overpay->CreateOrUpdate($request->all(), $request->id);
        return $this->autoResponse($data);
    }

    public function detailOverpayFee(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:overpayments,id'
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors());
        }
        // dd(222);
        $data = $this->repo_overpay->WhereDataWith([
            'historyPengembalian',
            'getBank',
            'getJamaah',
            'getInv',
        ], ['id' => $request->id])->first();
        return $this->autoResponse($data);
    }


}
