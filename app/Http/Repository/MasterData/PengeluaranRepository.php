<?php

namespace App\Http\Repository\MasterData;

use App\Http\Repository\BaseRepository;
use App\Models\MasterData\MasterPengeluaranPaket;

class PengeluaranRepository extends BaseRepository
{
    public function __construct(MasterPengeluaranPaket $model)
    {
        $this->model = $model;
    }
}
