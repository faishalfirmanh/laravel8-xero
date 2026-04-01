<?php

namespace App\Http\Repository\Config;

use App\Http\Repository\BaseRepository;
use App\Models\Config\TravelUser;


use DB;

class TravelUserRepository extends BaseRepository
{

    public $model;
    public function __construct(TravelUser $model)
    {
        $this->model = $model;
    }

}
