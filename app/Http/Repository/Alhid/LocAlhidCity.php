<?php

namespace App\Http\Repository\Alhid;

use App\Http\Repository\BaseRepository;
use App\Models\Alhidayah\AlhidCity;




class LocAlhidCity extends BaseRepository
{

    public $model;
    public function __construct(AlhidCity $model)
    {
        $this->model = $model;
    }

}
