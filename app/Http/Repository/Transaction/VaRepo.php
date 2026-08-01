<?php
namespace App\Http\Repository\Transaction;

use App\Http\Repository\BaseRepository;

use App\Models\Transaction\VaTransUser;

class VaRepo extends BaseRepository
{
    public function __construct(VaTransUser $model)
    {
        $this->model = $model;
    }


}
