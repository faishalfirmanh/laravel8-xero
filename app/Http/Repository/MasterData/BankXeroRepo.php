<?php

namespace App\Http\Repository\MasterData;

use App\Http\Repository\BaseRepository;
use App\Models\MasterData\BankXero;

class BankXeroRepo extends BaseRepository
{
    public function __construct(BankXero $model)
    {
        $this->model = $model;
    }

    public function firstCreate($req)
    {

        return $this->model->firstOrCreate(['account_id' => $req['account_id']], $req);
    }

}
