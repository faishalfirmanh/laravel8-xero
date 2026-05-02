<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Http\Repository\MasterData\CoaRepo;

use App\Http\Repository\Transaction\SpendMoneyRepo;
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
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;


class CoaController extends Controller
{

    use ApiResponse;
    protected $repo;

    public function __construct(CoaRepo $repo)
    {
        $this->repo = $repo;

    }

    function generateRandom4Digit()
    {
        return str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'account_type' => 'required|string|regex:/^(current_asset|fixed_asset|revenue|inventory|non_current_asset|prepayment|equity|description|direct_cost|expense|overhead|current_liability|liability|non_current_liability|other_income|sales)$/i',
            'account_type' => [
                'required',
                'string',
                'regex:/^(current_asset|fixed_asset|revenue|inventory|non_current_asset|prepayment|equity|description|direct_cost|expense|overhead|current_liability|liability|non_current_liability|other_income|sales)$/i'
            ],
            // 'code' => [
            //     'required',
            //     'string',
            //      Rule::unique('coas', 'code')->ignore($request->id)
            // ],
            'name' => 'required|string',
            'desc' => 'nullable|string'

        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }

        if ($request->id == NULL) {
            $request->merge(['code' => self::generateRandom4Digit()]);
        }

        $request['created_by'] = 1;// $request->user_login->id;
        $saved = $this->repo->CreateOrUpdate($request->all(), $request->id);
        return $this->autoResponse($saved);
    }



    public function getAllPaginateSelect2(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'required|integer',
            'keyword' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }
        //dd($request->menu);
        $where = [];
        if ($request->keyword != null) {
            $data = $this->repo->searchData($where, 10, $request->page, 'name', strtoupper($request->keyword));
        } else {
            $data = $this->repo->getAllDataWithDefault($where, 10, $request->page, 'name', 'ASC');//getDataPaginate("name",10,$request->keyword);
        }
        //$data['menu']= $request->menu;
        return $this->autoResponse($data);
        //return $this->success($data);
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
        //dd($request->menu);
        $where = [];
        if ($request->keyword != null) {
            $data = $this->repo->searchData($where, $request->limit, $request->page, 'name', strtoupper($request->keyword));
        } else {
            $data = $this->repo->getAllDataWithDefault($where, $request->limit, $request->page, 'name', 'ASC');//getDataPaginate("name",10,$request->keyword);
        }
        //$data['menu']= $request->menu;
        return $this->autoResponse($data);
        //return $this->success($data);
    }



    public function delete(Request $request)
    {
        $id = $request->id;

        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:coas,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }
        $blog = $this->repo->find($id);

        if ($blog) {
            $data = $this->repo->delete($id);
            return $this->autoResponse($data);
        }

        return $this->error('hotel not found', 404);
    }


    public function detail(Request $request)
    {
        $data = $this->repo->find($request->id);
        return $this->autoResponse($data);
    }

    //
}
