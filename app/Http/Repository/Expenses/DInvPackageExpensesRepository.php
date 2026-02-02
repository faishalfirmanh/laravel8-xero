<?php

namespace App\Http\Repository\Expenses;

use App\Http\Repository\BaseRepository;
use App\Models\Expenses\DInvPackageExpenses;

use DB;

class DInvPackageExpensesRepository extends BaseRepository
{
    public $model;
    public function __construct(DInvPackageExpenses $model)
    {
        $this->model = $model;
    }

}
