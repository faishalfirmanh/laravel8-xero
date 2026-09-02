<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use App\Http\Repository\Config\ConfigCurrencyRepository;
use App\Http\Repository\Config\ConfigMasterCurrencyRepository;
use Illuminate\Http\Request;
use Validator;
use App\Traits\ApiResponse;
class ConfigCurrencyApiController
{
    //

    protected $repo, $repo_master;
    use ApiResponse;
    public function __construct(ConfigCurrencyRepository $repo, ConfigMasterCurrencyRepository $repo_master)
    {
        $this->repo = $repo;
        $this->repo_master = $repo_master;
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
            $data = $this->repo_master->searchData($where, $request->limit, $request->page, 'code_curr', strtoupper($request->keyword));
        } else {
            $data = $this->repo_master->getAllDataWithDefault($where, $request->limit, $request->page, 'code_curr', 'ASC');//getDataPaginate("name",10,$request->keyword);
        }
        return $this->autoResponse($data);
    }

    public function savedMasterConfigCurrency(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:m_currency,id',
            'nominal_currency' => 'required|numeric',
            'satu_rupiah' => 'nullable|numeric',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }

        $savedd = $this->repo_master->CreateOrUpdate($request->all(), $request->id);
        return $this->autoResponse($savedd);
    }

    public function fingByIdMaster($idMaster)
    {
        $get = $this->repo_master->find($idMaster);
        return $this->autoResponse($get);
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
