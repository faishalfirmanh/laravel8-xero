<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Repository\MasterData\HotelRepository;
use Illuminate\Http\Request;
use Validator;
use App\Traits\ApiResponse;
use Illuminate\Validation\Rule;
class HotelApiController extends Controller
{
    //

    protected $repo;
    use ApiResponse;
    public function __construct(HotelRepository $repo)
    {
        $this->repo = $repo;
    }
    public function getData()
    {

    }

    public function SearchHotel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'keyword' => 'nullable|string'
        ]);
        $where = [];
        $data = $this->repo->searchData($where, $request->limit, $request->page, 'name', strtoupper($request->keyword));
        return $this->autoResponse($data);

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
            $data = $this->repo->searchData($where, $request->limit, $request->page, 'name', strtoupper($request->keyword));
        } else {
            $data = $this->repo->getAllDataWithDefault($where, $request->limit, $request->page, 'name', 'ASC');//getDataPaginate("name",10,$request->keyword);
        }
        return $this->autoResponse($data);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                Rule::unique('hotels', 'name')->ignore($request->id)
            ],
            'type_location_hotel' => 'integer|between:1,5',
            'id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }

        $saved = $this->repo->CreateOrUpdate($request->all(), $request->id);
        return $this->autoResponse($saved);
    }

    public function delete(Request $request)
    {
        $id = $request->id;

        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:hotels,id',
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
}
