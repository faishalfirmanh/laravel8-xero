<?php

namespace App\Http\Repository\Revenue;

use App\Http\Repository\BaseRepository;
use App\Models\InvoicesAllFromXero;

use DB;

class InvoiceXeroLocalRepo extends BaseRepository
{

    public $model;
    public function __construct(InvoicesAllFromXero $model)
    {
        $this->model = $model;
    }

}
