<?php

namespace App\Http\Repository\MasterData;

use App\Http\Repository\BaseRepository;
use App\Models\MasterData\MasterPengeluaranPaket;

use DB;

class PengeluaranRepository extends BaseRepository {

    public $model;
    public function __construct(MasterPengeluaranPaket $model)
    {
        $this->model = $model;
    }

}
