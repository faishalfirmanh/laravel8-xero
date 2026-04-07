<?php

namespace App\Http\Repository\MasterData;

use App\Http\Repository\BaseRepository;
use App\Models\MasterData\TrackingCategory;

class TrackingRepo extends BaseRepository
{
    public function __construct(TrackingCategory $model)
    {
        $this->model = $model;
    }

    public function getLastIdPlusOne()
    {
        $no = $this->model->latest()->first() == null ? 1 : $this->model->latest()->first()->id + 1;
        return $no;
    }
}
