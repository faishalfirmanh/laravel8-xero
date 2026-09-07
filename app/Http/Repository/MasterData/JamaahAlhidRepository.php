<?php

namespace App\Http\Repository\MasterData;

use App\Http\Repository\BaseRepository;
use App\Models\DataJamaah;


class JamaahAlhidRepository extends BaseRepository
{

    public $model;
    public function __construct(DataJamaah $model)
    {
        $this->model = $model;
    }

    public function searchDataAlhidd(
        $where = [],
        $per_page = 5,
        $page = 1,
        $search_column = "",
        $keyword = "",
        $modelWith = []
    ) {
        $per_page = max(1, min((int) $per_page, 10));

        $keyword = trim($keyword);

        $query = $this->model
            ->with($modelWith)
            ->where($where);

        if ($keyword !== '' && $search_column !== '') {

            $query->where(
                $search_column,
                'LIKE',
                '%' . $keyword . '%'
            );
        }

        return $query
            ->orderBy($search_column, 'ASC')
            ->limit($per_page)
            ->get();
    }

    public function getAllDataWithDefault(
        $where = array(),
        $per_page = 10,
        $offset = 1,
        $sort_column,
        $sort_order = "ASC",
        $modelWith = [],
        $limit = null
    ) {
        $query = $this->model
            ->with($modelWith)
            ->where($where)
            ->orderBy($sort_column, $sort_order)
            ->offset($offset);

        if ($limit !== null) {
            $query->limit($limit);
        } else {
            $query->limit($per_page);
        }

        return $query->get();
    }
}
