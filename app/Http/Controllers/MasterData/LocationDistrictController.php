<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;
use App\Traits\ApiResponse;
use App\Http\Repository\MasterData\LocationDistrictRepository;

class LocationDistrictController extends Controller
{

    protected $repo;
    use ApiResponse;

    public function __construct(LocationDistrictRepository $repo)
    {
        $this->repo = $repo;
    }

    public function getAllSubdisByCityId(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'id_city' => 'required|numeric|exists:location_city,id',
        ]);

        if ($validator->fails()) {
           return $this->error($validator->errors());
        }
        $get = $this->repo->getAllByIdCity($request->id_city);
        return $this->autoResponse($get);
    }

    public function SearchSubdis(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'id_city' => 'required|numeric|exists:location_city,id',
            'keyword' => 'string|nullable'
        ]);

        if ($validator->fails()) {
           return $this->error($validator->errors());
        }

        $get = $this->repo->search($request->id_city,strtoupper($request->keyword));
        return $this->autoResponse($get);
    }

    public function getSubdisById(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'id' => 'required|numeric|exists:location_districts,id',
        ]);

        if ($validator->fails()) {
           return $this->error($validator->errors());
        }
        $get = $this->repo->getById($request->id);
        return $this->autoResponse($get);
    }
}
