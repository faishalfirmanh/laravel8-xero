<?php
namespace App\Http\Repository\Transaction;

use App\Http\Repository\BaseRepository;


use App\Models\Transaction\TransactionNominalBankAccount;

class TransBankRepo extends BaseRepository
{
    public function __construct(TransactionNominalBankAccount $model)
    {
        $this->model = $model;
    }

    public function wherenDataIn($column, $value)
    {
        return $this->model->whereIn($column, $value);
    }

}
