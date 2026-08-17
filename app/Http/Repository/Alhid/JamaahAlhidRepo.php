<?php

namespace App\Http\Repository\Alhid;

use App\Http\Repository\BaseRepository;
use App\Models\Alhidayah\DataJamaahAlhidayah;




class JamaahAlhidRepo extends BaseRepository
{

    public $model;
    public function __construct(DataJamaahAlhidayah $model)
    {
        $this->model = $model;
    }

}
