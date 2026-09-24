<?php

namespace App\Http\Repository\MasterData\Toko;

use App\Http\Repository\BaseRepository;
use App\Models\MasterData\Toko\Unit;


class UnitRepo extends BaseRepository
{
    public function __construct(Unit $model)
    {
        $this->model = $model;
    }

}
