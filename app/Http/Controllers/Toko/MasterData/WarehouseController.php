<?php

namespace App\Http\Controllers\Toko\MasterData;
use App\Http\Controllers\Controller;
use App\Http\Repository\MasterData\Toko\WarehouseRepo;
use App\Services\GlobalService;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\InvoicesDuplicateController;
use Validator;

class WarehouseController extends Controller
{

    use ApiResponse;

    protected $repo, $global;



    public function __construct(WarehouseRepo $repo, GlobalService $global)
    {
        $this->repo = $repo;
        $this->global = $global;
    }

    public function getAll(Request $request)
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

    public function getById(Request $request)
    {
        $data = $this->repo->find($request->id);
        return $this->autoResponse($data);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|numeric',
            'name' => 'required|string',
            'address' => 'nullable|string',
            'is_active' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }

        if ($request->id == NULL) {
            $request->merge(['code' => $this->global->generateRandomCode()]);
        }

        $saved = $this->repo->CreateOrUpdate($request->all(), $request->id);
        return $this->autoResponse($saved);
    }


}
