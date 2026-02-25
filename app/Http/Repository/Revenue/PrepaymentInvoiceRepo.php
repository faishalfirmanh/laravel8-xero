<?php

namespace App\Http\Repository\Revenue;

use App\Http\Repository\BaseRepository;
use App\Models\PrepaymentInvoice;

use DB;

class PrepaymentInvoiceRepo extends BaseRepository
{

    public $model;
    public function __construct(PrepaymentInvoice $model)
    {
        $this->model = $model;
    }

}
