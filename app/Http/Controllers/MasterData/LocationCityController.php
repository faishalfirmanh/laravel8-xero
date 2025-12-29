<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Traits\ApiResponse;
use App\Http\Repository\MasterData\LocationCityRepository;

use Validator;

class LocationCityController extends Controller
{
     protected $repo;
     use ApiResponse;

    public function __construct(LocationCityRepository $repo)
    {
        $this->repo = $repo;
    }


    public function getAllCityByIdProf(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'id_prov' => 'required|numeric|exists:location_city,id_prov',
        ]);

        if ($validator->fails()) {
           return $this->error($validator->errors());
        }
        $get = $this->repo->getAllByIdProf($request->id_prov);
        return $this->autoResponse($get);
    }

    public function SearchCityByIdProf(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'id_prov' => 'required|numeric|exists:location_city,id_prov',
            'keyword' => 'string|nullable'
        ]);

        if ($validator->fails()) {
           return $this->error($validator->errors());
        }
        $get = $this->repo->search($request->id_prov,strtoupper($request->keyword));
        return $this->autoResponse($get);
    }

    public function getCityById(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'id' => 'required|numeric|exists:location_city,id',
        ]);

        if ($validator->fails()) {
           return $this->error($validator->errors());
        }
        $get = $this->repo->getById($request->id);
        return $this->autoResponse($get);
    }
}
