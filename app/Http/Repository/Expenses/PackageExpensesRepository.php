<?php

namespace App\Http\Repository\Expenses;

use App\Http\Repository\BaseRepository;
use App\Models\Expenses\PackageExpensesXero;

use DB;

class PackageExpensesRepository extends BaseRepository
{

    public $model;
    public function __construct(PackageExpensesXero $model)
    {
        $this->model = $model;
    }

}
