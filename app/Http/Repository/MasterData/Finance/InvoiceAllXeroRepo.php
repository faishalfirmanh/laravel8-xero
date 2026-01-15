<?php

namespace App\Http\Repository\MasterData;

use App\Http\Repository\BaseRepository;
use App\Models\InvoicesAllFromXero;
use DB;

class InvoiceAllXeroRepo extends BaseRepository{

    public function __construct(InvoicesAllFromXero $model)
    {
        parent::__construct($model);
    }


}
