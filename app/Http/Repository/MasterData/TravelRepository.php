<?php

namespace App\Http\Repository\MasterData;

use App\Http\Repository\BaseRepository;
use App\Models\MasterData\TravelName;

class TravelRepository extends BaseRepository
{
    public function __construct(TravelName $model)
    {
        $this->model = $model;
    }

}
