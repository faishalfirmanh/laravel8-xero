<?php

namespace App\Http\Repository\Alhid;

use App\Http\Repository\BaseRepository;

use App\Models\Alhidayah\AlhidVill;




class LocAlhidVill extends BaseRepository
{

    public $model;
    public function __construct(AlhidVill $model)
    {
        $this->model = $model;
    }

}
