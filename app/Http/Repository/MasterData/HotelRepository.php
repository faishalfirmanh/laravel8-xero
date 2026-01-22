<?php

namespace App\Http\Repository\MasterData;

use App\Http\Repository\BaseRepository;
use App\Models\MasterData\Hotel;


use DB;

class HotelRepository extends BaseRepository
{

    public $model;
    public function __construct(Hotel $model)
    {
        $this->model = $model;
    }

}
