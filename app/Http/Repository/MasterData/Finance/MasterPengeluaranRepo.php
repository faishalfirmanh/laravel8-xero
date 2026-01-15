<?php

namespace App\Http\Repository\MasterData;

use App\Http\Repository\BaseRepository;
use App\Models\MasterData\MasterPengeluaranPaket;
use DB;

class MasterPengeluaranRepo extends BaseRepository{

    public function __construct(MasterPengeluaranPaket $model)
    {
        parent::__construct($model);
    }
}
