<?php

namespace App\Http\Repository\MasterData;

use App\Http\Repository\BaseRepository;
use App\Models\MasterData\Location\Villages;

use DB;

class LocationVillageRepository {

    protected $model;
    public function __construct(Villages $model)
    {
        $this->model = $model;
    }

    public function getAll()
    {
        return $this->model->get();
    }

    public function getById($id)
    {
        return $this->model->find($id);
    }

     public function getAllByIdSubdistrict($id_subdis)
    {
        return $this->model->where('id_kecamatan',$id_subdis)->get();
    }

    public function search($id_kec,$keyword)
    {
        return $this->model->where('id_kecamatan',$id_kec)->where('name','like','%'.$keyword .'%')->get();
    }


}
