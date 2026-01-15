<?php

namespace App\Http\Repository\MasterData;

use App\Http\Repository\BaseRepository;
use App\Models\MasterData\DetailSpendingInvoice;
use DB;

class DetailSpendingInvRepo extends BaseRepository{

    public function __construct(DetailSpendingInvoice $model)
    {
        parent::__construct($model);
    }

}
