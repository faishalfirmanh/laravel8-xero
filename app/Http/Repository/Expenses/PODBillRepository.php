<?php

namespace App\Http\Repository\Expenses;

use App\Http\Repository\BaseRepository;

use App\Models\Expenses\Purchase\Bill\DBill;



class PODBillRepository extends BaseRepository
{
    public $model;
    public function __construct(DBill $model)
    {
        $this->model = $model;
    }

    public function wherenDataIn($column, $value)
    {
        return $this->model->whereIn($column, $value);
    }
}
