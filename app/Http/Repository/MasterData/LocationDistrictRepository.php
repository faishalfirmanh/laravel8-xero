<?php

namespace App\Http\Repository\MasterData;

use App\Http\Repository\BaseRepository;
use App\Models\MasterData\Location\Subdistrict;

use DB;

class LocationDistrictRepository {

    protected $model;
    public function __construct(Subdistrict $model)
    {
        $this->model = $model;
    }

    public function getAllByIdCity($id_prof)
    {
        return $this->model->where('kabupaten_id',$id_prof)->get();
    }

    public function getById($id)
    {
        return $this->model->find($id);
    }

    public function search($kab_id,$keyword)
    {
        return $this->model->where('kabupaten_id',$kab_id)->where('name','like','%'.$keyword .'%')->get();
    }

}
