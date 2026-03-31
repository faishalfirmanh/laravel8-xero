<?php

namespace App\Http\Repository\MasterData;

use App\Http\Repository\BaseRepository;
use App\Models\MasterData\BusinessLine;

class BusinessRepository extends BaseRepository
{
    public function __construct(BusinessLine $model)
    {
        $this->model = $model;
    }

}
