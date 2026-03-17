<?php

namespace App\Http\Repository\MasterData;

use App\Http\Repository\BaseRepository;
use App\Models\MasterData\BankXero;

class BankXeroRepo extends BaseRepository
{
    public function __construct(BankXero $model)
    {
        $this->model = $model;
    }

  }
