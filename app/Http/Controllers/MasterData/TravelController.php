<?php
namespace App\Http\Controllers\MasterData;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Repository\MasterData\TravelRepository;

use Validator;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;
use App\Services\GlobalService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\PaymentParams;
use Illuminate\Support\Facades\Http;
use App\ConfigRefreshXero;
use Barryvdh\DomPDF\Facade\Pdf;

class TravelController extends Controller {

   // protected $repo, $repo_detail, $service_global, $repo_jamaah;
    use ConfigRefreshXero;
    use ApiResponse;

    protected $repo;// $repo_trans, $repo_tracking;


    public function __construct(TravelRepository $repo)
    {
        $this->repo = $repo;
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
        return $this->autoResponse($data);
    }




     public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string',
            'is_active'      => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 400);
        }
         $request['created_by']= $request->user_login->id;
        $saved = $this->repo->CreateOrUpdate($request->all(), $request->id);

        return $this->autoResponse($saved);
    }




    public function destroy(Request $request)
    {
         $id = $request->id;

        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:travel_names,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 400);
        }

        $data = $this->repo->delete($id);
        return $this->autoResponse($data);

    }

    public function detail(Request $request)
    {
        $data = $this->repo->find($request->id);
        return $this->autoResponse($data);
    }





}
