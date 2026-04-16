<?php

namespace App\Http\Repository\Config;

use App\Http\Repository\BaseRepository;
use App\Models\MasterData\Menu;


use DB;

class MenuRepository extends BaseRepository
{

    public $model;
    public function __construct(Menu $model)
    {
        $this->model = $model;
    }

}
