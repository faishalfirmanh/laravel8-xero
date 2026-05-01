<?php

namespace App\Http\Repository\Expenses;

use App\Http\Repository\BaseRepository;

use App\Models\Expenses\Purchase\Bill\PBill;


class POPBillRepository extends BaseRepository
{
    public $model;
    public function __construct(PBill $model)
    {
        $this->model = $model;
    }

}
