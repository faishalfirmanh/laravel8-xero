<?php

namespace App\Http\Repository\Revenue;

use App\Http\Repository\BaseRepository;


use App\Models\MasterData\ItemDetailInvoices;
use DB;

class InvoiceDXeroLocalRepo extends BaseRepository
{

    public $model;
    public function __construct(ItemDetailInvoices $model)
    {
        $this->model = $model;
    }

    public function wherenDataIn($column, $value)
    {
        return $this->model->whereIn($column, $value);
    }

}
