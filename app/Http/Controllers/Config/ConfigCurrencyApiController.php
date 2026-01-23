<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use App\Http\Repository\Config\ConfigCurrencyRepository;
use Illuminate\Http\Request;
use Validator;
use App\Traits\ApiResponse;
class ConfigCurrencyApiController
{
    //

    protected $repo;
    use ApiResponse;
    public function __construct(ConfigCurrencyRepository $repo)
    {
        $this->repo = $repo;
    }
    public function getData()
    {

    }

    public function getAllPaginate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'required|integer',
            'keyword' => 'nullable|string',
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

    public function fingById(Request $request)
    {
        $get = $this->repo->find(1);
        return $this->autoResponse($get);
    }


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nominal_rupiah_1_riyal' => 'required|integer',
            //'id' => 'required|exists:config_currencies,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }

        $saved = $this->repo->CreateOrUpdate($request->all(), 1);
        return $this->autoResponse($saved);
    }

}
