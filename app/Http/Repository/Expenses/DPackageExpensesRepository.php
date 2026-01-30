<?php

namespace App\Http\Repository\Expenses;

use App\Http\Repository\BaseRepository;
use App\Models\Expenses\DPackageExpensesXero;

use DB;

class DPackageExpensesRepository extends BaseRepository
{

    public $model;
    public function __construct(DPackageExpensesXero $model)
    {
        $this->model = $model;
    }

}
