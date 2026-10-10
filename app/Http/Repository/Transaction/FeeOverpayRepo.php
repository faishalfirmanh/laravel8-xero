<?php
namespace App\Http\Repository\Transaction;

use App\Http\Repository\BaseRepository;
use App\Models\Transaction\FeeOverpay;



class FeeOverpayRepo extends BaseRepository
{
    public function __construct(FeeOverpay $model)
    {
        $this->model = $model;
    }

    public function sumDataWhereDinamis($where = array(), string $kolom)
    {
        $data = $this->model->where($where)->sum($kolom);
        return $data;
    }


}
