<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Http\Repository\MasterData\TrackingRepo;

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



class TrackingController extends Controller
{

    use ApiResponse;
    protected $repo;

    public function __construct(TrackingRepo $repo)
    {
        $this->repo = $repo;

    }


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name_parent_category'=> 'required|string',
            'lines_category'               => 'required|array|min:1',
            'lines_category.*.id_parent'        => 'required|string',
            'lines_category.*.item_name_category'        => 'required|string|max:255',
            'lines_category.*.item_uuid_category'        => 'required|string|max:100',

        ]);

if ($validator->fails()) {
    return response()->json([
        'status'  => 'error',
        'message' => 'Validasi Gagal',
        'errors'  => $validator->errors()->toArray()
    ], 422);
}



        $request['created_by']=1;// $request->user_login->id;
        $saved = $this->repo->CreateOrUpdate($request->all(), $request->id);
        return $this->autoResponse($saved);
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
            $data = $this->repo->searchData($where, $request->limit, $request->page, 'name_parent_category', strtoupper($request->keyword));
        } else {
            $data =
            $this->repo->getAllDataWithDefault($where, $request->limit, $request->page, 'name_parent_category', 'ASC');//getDataPaginate("name",10,$request->keyword);
        }
        return $this->autoResponse($data);
    }



      public function delete(Request $request)
    {
        $id = $request->id;

        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:tracking_categories,id',
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
