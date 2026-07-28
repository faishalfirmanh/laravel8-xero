<?php
namespace App\Http\Repository\Transaction;

use App\Http\Repository\BaseRepository;
use App\Models\Transaction\Overpayment;


class OverPayRepo extends BaseRepository
{
    public function __construct(Overpayment $model)
    {
        $this->model = $model;
    }


}
