<?php

namespace App\Http\Repository\MasterData\Finance;

use App\Http\Repository\BaseRepository;
use App\Models\InvoicesAllFromXero;
use DB;

class InvoiceAllXeroRepo extends BaseRepository{

    public function __construct(InvoicesAllFromXero $model)
    {
        parent::__construct($model);
    }

     public function sumDataWhereIn($list_id = array(), string $kolom)
    {
        $data = $this->model->whereIn('invoice_uuid',$list_id)->sum($kolom);
        return $data;
    }

}
