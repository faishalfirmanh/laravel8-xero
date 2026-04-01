<?php

namespace App\Http\Repository;

use App\Http\Repository\BaseRepository;
use App\Models\User;


use DB;

class UserRepository extends BaseRepository
{

    public $model;
    public function __construct(User $model)
    {
        $this->model = $model;
    }

}
