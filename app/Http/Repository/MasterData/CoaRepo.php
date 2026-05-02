<?php

namespace App\Http\Repository\MasterData;

use App\Http\Repository\BaseRepository;
use App\Models\MasterData\Coa;

class CoaRepo extends BaseRepository
{
    public function __construct(Coa $model)
    {
        $this->model = $model;
    }

    public function firstCreate($req)
    {

        return $this->model->firstOrCreate(['account_uuid' => $req['account_uuid']], $req);
    }

}
