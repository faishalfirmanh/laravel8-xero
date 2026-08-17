<?php

namespace App\Http\Repository\Alhid;

use App\Http\Repository\BaseRepository;

use App\Models\Alhidayah\AlhidSub;




class LocAlhidSubd extends BaseRepository
{

    public $model;
    public function __construct(AlhidSub $model)
    {
        $this->model = $model;
    }

}
