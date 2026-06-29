<?php
namespace App\Http\Repository\Transaction;

use App\Http\Repository\BaseRepository;



use App\Models\Transaction\TransactionTransferBank;

class TransferBankRepo extends BaseRepository
{
    public function __construct(TransactionTransferBank $model)
    {
        $this->model = $model;
    }

    public function wherenDataIn($column, $value)
    {
        return $this->model->whereIn($column, $value);
    }

}
