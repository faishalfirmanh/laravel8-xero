<?php

namespace App\Http\Repository\Config;

use App\Http\Repository\BaseRepository;



use App\Models\MasterData\MasterCurrency;


class ConfigMasterCurrencyRepository extends BaseRepository
{

    public $model;
    public function __construct(MasterCurrency $model)
    {
        $this->model = $model;
    }

}
