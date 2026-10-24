<?php
namespace App\Http\Repository\Transaction;

use App\Http\Repository\BaseRepository;


use App\Models\Transaction\TransactionNominalBankAccount;

class TransBankRepo extends BaseRepository
{
    public function __construct(TransactionNominalBankAccount $model)
    {
        $this->model = $model;
    }

    public function wherenDataIn($column, $value)
    {
        return $this->model->whereIn($column, $value);
    }

    public function searchData($where = array(), $per_page = 10, $offset = 1, $search_column = "", $keyword = "", $modelWith = [], $whereNull = [])
    {
        $query = $this->model->with($modelWith)->where($where);

        foreach ($whereNull as $col) {
            $query->whereNull($col);
        }

        return $query
            ->whereRaw("LOWER($search_column) like ?", ['%' . strtolower($keyword) . '%'])
            ->paginate($per_page);
    }

    public function getAllDataWithDefault($where = array(), $per_page = 10, $offset = 1, $sort_column = 'id', $sort_order = "ASC", $modelWith = [], $whereNull = [])
    {
        $query = $this->model->with($modelWith)->where($where);

        foreach ($whereNull as $col) {
            $query->whereNull($col);
        }

        return $query->orderBy($sort_column, $sort_order)->paginate($per_page);
    }

}
