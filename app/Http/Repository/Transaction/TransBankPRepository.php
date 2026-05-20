<?php

namespace App\Http\Repository\Transaction;

use App\Http\Repository\BaseRepository;

use App\Models\Transaction\TransactionBankTransP;



class TransBankPRepository extends BaseRepository
{
    public $model;
    public function __construct(TransactionBankTransP $model)
    {
        $this->model = $model;
    }

    public function wherenDataIn($column, $value)
    {
        return $this->model->whereIn($column, $value);
    }
}
