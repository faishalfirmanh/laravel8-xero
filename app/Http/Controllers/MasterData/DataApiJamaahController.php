<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;
use App\Traits\ApiResponse;
use Illuminate\Validation\Rule;
use App\Http\Repository\MasterData\DataJamaahXeroRepository;
class DataApiJamaahController extends Controller//from xero when create transaction invoice hotel
{
    //

    protected $repo;
    use ApiResponse;
    public function __construct(DataJamaahXeroRepository $repo)
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
        $where = [];
        if ($request->keyword != null) {
            $data = $this->repo->searchData($where, $request->limit, $request->page, 'full_name', strtoupper($request->keyword));
        } else {
            $data = $this->repo->getAllDataWithDefault($where, $request->limit, $request->page, 'full_name', 'ASC');//getDataPaginate("name",10,$request->keyword);
        }
        return $this->autoResponse($data);
    }

    public function getById(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:data_jamaah_xeros,id'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }

        $data = $this->repo->WhereDataWith(['transHotel'], ['id' => $request->id])->first();
        return $this->autoResponse($data);
    }

}
