<?php

namespace App\Http\Repository\MasterData;

use App\Http\Repository\BaseRepository;
use App\Models\MasterData\MasterMaskapai;

class MaskapaiRepository extends BaseRepository
{
    public $model;

    public function __construct(MasterMaskapai $model)
    {
        $this->model = $model;
    }
}
