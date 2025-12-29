<?php

namespace App\Http\Repository\MasterData;

use App\Http\Repository\BaseRepository;
use App\Models\MasterData\Location\Province;

use DB;

class LocationProvRepository {

    protected $model;
    public function __construct(Province $model)
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

    public function search($keyword)
    {
        return $this->model->where('name','like','%'.$keyword .'%')->get();
    }

}
