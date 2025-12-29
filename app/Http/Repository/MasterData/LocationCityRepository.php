<?php

namespace App\Http\Repository\MasterData;

use App\Http\Repository\BaseRepository;
use App\Models\MasterData\Location\City;

use DB;

class LocationCityRepository {

    protected $model;
    public function __construct(City $model)
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

    public function search($id_prof,$keyword)
    {
        return $this->model->where('id_prov',$id_prof)->where('name','like','%'.$keyword .'%')->get();
    }

    public function getAllByIdProf($id_prof)
    {
        return $this->model->where('id_prov',$id_prof)->get();
    }
}
