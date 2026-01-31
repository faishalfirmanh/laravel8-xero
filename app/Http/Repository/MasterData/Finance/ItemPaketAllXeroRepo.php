<?php

namespace App\Http\Repository\MasterData\Finance;

use App\Http\Repository\BaseRepository;
use App\Models\ItemsPaketAllFromXero;
use DB;

class ItemPaketAllXeroRepo extends BaseRepository{

    public function __construct(ItemsPaketAllFromXero $model)
    {
        parent::__construct($model);
    }


}
