<?php

namespace App\Http\Repository\MasterData;

use App\Http\Repository\BaseRepository;
use App\Models\MasterData\DataJamaahXero;


use DB;

class JamaahXeroRepository extends BaseRepository
{

    public $model;
    public function __construct(DataJamaahXero $model)
    {
        $this->model = $model;
    }

    public function firstCreate($req)
    {

        return $this->model->firstOrCreate(['uuid_contact' => $req['uuid_contact']], $req);
    }
}
