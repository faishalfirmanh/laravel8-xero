<?php

namespace App\Http\Repository\Config;

use App\Http\Repository\BaseRepository;
use App\Models\Config\RoleMenus;


use DB;

class RoleMenuRepository extends BaseRepository
{

    public $model;
    public function __construct(RoleMenus $model)
    {
        $this->model = $model;
    }

}
