<?php

namespace App\Http\Repository\MasterData;

use App\Http\Repository\BaseRepository;
use App\Models\MasterData\Coa;

class CoaRepo extends BaseRepository
{
    public function __construct(Coa $model)
    {
        $this->model = $model;
    }

    public function firstCreate($req)
    {

        return $this->model->firstOrCreate(['account_uuid' => $req['account_uuid']], $req);
    }
    public function getAllDataWithDefault($where = [], $per_page = 10, $offset = 1, $sort_column, $sort_order = "ASC", $modelWith = [])
    {
        $query = $this->model->with($modelWith);

        if (is_callable($where)) {
            // Handle Closure dari chekWhere()
            $query->where($where);
        } else {
            foreach ($where as $column => $value) {
                if (is_array($value)) {
                    $query->whereIn($column, $value);
                } else {
                    $query->where($column, $value);
                }
            }
        }

        return $query->orderBy($sort_column, $sort_order)
            ->paginate($per_page);
    }


    public function searchData($where = array(), $per_page = 10, $offset = 1, $search_column = "", $keyword = "", $modelWith = [])
    {
        $query = $this->model->with($modelWith);

        // Looping untuk mengecek apakah ada multiple values (array) untuk whereIn
        foreach ($where as $column => $value) {
            if (is_array($value)) {
                $query->whereIn($column, $value);
            } else {
                $query->where($column, $value);
            }
        }

        // Menggunakan parameter binding (?) untuk mencegah SQL Injection
        $keywordClean = strtolower($keyword);

        // Hapus offset() dan limit()
        return $query->whereRaw("LOWER({$search_column}) LIKE ?", ['%' . $keywordClean . '%'])
            ->paginate($per_page);
    }

}
