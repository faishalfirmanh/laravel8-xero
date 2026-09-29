<?php
namespace App\Http\Repository\Transaction;

use App\Http\Repository\BaseRepository;

use App\Models\Transaction\SummaryNominalBank;


class SummaryBankRepo extends BaseRepository
{
    public function __construct(SummaryNominalBank $model)
    {
        $this->model = $model;
    }

    public function wherenDataIn($column, $value)
    {
        return $this->model->whereIn($column, $value);
    }

    public function updateCreate($req)
    {
        return $this->model->updateOrCreate(
            ['bank_id' => $req['bank_id']],
            $req
        );
    }

}
