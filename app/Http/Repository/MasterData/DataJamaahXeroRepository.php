<?php

namespace App\Http\Repository\MasterData;

use App\Http\Repository\BaseRepository;
use App\Models\MasterData\DataJamaahXero;


use DB;

class DataJamaahXeroRepository extends BaseRepository
{

    public $model;
    public function __construct(DataJamaahXero $model)
    {
        $this->model = $model;
    }

    public function firstOrCreata($param_update,$create){
       $save =  $this->model->firstOrCreate($param_update,$create);
       return $save;
    }

}
