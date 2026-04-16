<?php

namespace App\Http\Repository\Config;

use App\Http\Repository\BaseRepository;
use App\Models\Config\RoleUsers;




class RelasiRoleUserRepository extends BaseRepository
{

    public $model;
    public function __construct(RoleUsers $model)
    {
        $this->model = $model;
    }

}
