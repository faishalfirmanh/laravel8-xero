<?php

namespace App\Http\Repository\MasterData;

use App\Http\Repository\BaseRepository;
use App\Models\DetailSpendingInvoice;
use DB;

class DetailSpendingInvRepo extends BaseRepository
{

    public function __construct(DetailSpendingInvoice $model)
    {
        parent::__construct($model);
    }

}
