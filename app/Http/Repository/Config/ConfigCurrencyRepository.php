<?php

namespace App\Http\Repository\Config;

use App\Http\Repository\BaseRepository;
use App\Models\Config\ConfigCurrency;


use DB;

class ConfigCurrencyRepository extends BaseRepository
{

    public $model;
    public function __construct(ConfigCurrency $model)
    {
        $this->model = $model;
    }

}
