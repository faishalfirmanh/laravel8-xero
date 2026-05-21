<?php

namespace App\Http\Repository\Transaction;

use App\Http\Repository\BaseRepository;


use App\Models\Transaction\TransactionBankTransD;


class TransBankDRepository extends BaseRepository
{
    public $model;
    public function __construct(TransactionBankTransD $model)
    {
        $this->model = $model;
    }

    public function wherenDataIn($column, $value)
    {
        return $this->model->whereIn($column, $value);
    }

}
