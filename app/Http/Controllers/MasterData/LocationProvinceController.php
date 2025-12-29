<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;
use App\Traits\ApiResponse;
use App\Http\Repository\MasterData\LocationProvRepository;
class LocationProvinceController extends Controller
{
    protected $repo;
    use ApiResponse;

    public function __construct(LocationProvRepository $repo)
    {
        $this->repo = $repo;
    }

     public function getAllProf(Request $request)
    {
        $get = $this->repo->getAll();
        return $this->autoResponse($get);
    }

    public function SearchProf(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'keyword' => 'string|nullable'
        ]);

        if ($validator->fails()) {
           return $this->error($validator->errors());
        }

        $get = $this->repo->search(strtoupper($request->keyword));
        return $this->autoResponse($get);
    }

    public function getProvById(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'id' => 'required|numeric|exists:location_provinces,id',
        ]);

        if ($validator->fails()) {
           return $this->error($validator->errors());
        }
        $get = $this->repo->getById($request->id);
        return $this->autoResponse($get);
    }

}
