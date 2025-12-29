<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Validator;
use App\Traits\ApiResponse;
use App\Http\Repository\MasterData\LocationVillageRepository;
class LocationVillageController extends Controller
{


     protected $repo;
    use ApiResponse;

    public function __construct(LocationVillageRepository $repo)
    {
        $this->repo = $repo;
    }


     /** ---------- Village ------------- */
    public function getAllVillageBySubdisId(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'id_kec' => 'required|numeric|exists:location_districts,id',
        ]);

        if ($validator->fails()) {
           return $this->error($validator->errors());
        }
        $get = $this->repo->getAllByIdSubdistrict($request->id_kec);
        return $this->autoResponse($get);
    }

    public function SearchVillage(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'id_kec' => 'required|numeric|exists:location_districts,id',
            'keyword' => 'string|nullable'
        ]);

        if ($validator->fails()) {
           return $this->error($validator->errors());
        }

        $get = $this->repo->search($request->id_kec,strtoupper($request->keyword));
        return $this->autoResponse($get);
    }

    public function getVillageById(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'id' => 'required|numeric|exists:location_villages,id',
        ]);

        if ($validator->fails()) {
           return $this->error($validator->errors());
        }
        $get = $this->repo->getById($request->id);
        return $this->autoResponse($get);
    }
}
