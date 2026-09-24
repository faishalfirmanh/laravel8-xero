<?php

namespace App\Http\Repository\MasterData\Toko;

use App\Http\Repository\BaseRepository;
use App\Models\MasterData\Toko\Warehouse;

class WarehouseRepo extends BaseRepository
{
    public function __construct(Warehouse $model)
    {
        $this->model = $model;
    }

}
