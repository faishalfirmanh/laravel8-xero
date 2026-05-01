<?php
namespace App\Http\Repository\Transaction;

use App\Http\Repository\BaseRepository;

use App\Models\Transaction\TransactionAllCoa;

class TransCoaRepo extends BaseRepository
{
    public function __construct(TransactionAllCoa $model)
    {
        $this->model = $model;
    }

}
