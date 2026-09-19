<?php

namespace App\Http\Repository\Expenses;

use App\Http\Repository\BaseRepository;
use App\Models\Expenses\Purchase\Bill\PBill;

class POPBillRepository extends BaseRepository
{
    public $model;

    public function __construct(PBill $model)
    {
        $this->model = $model;
    }

    /**
     * Helper: apply filter date range ke query
     */
    private function applyDateRange($query, $date_start = null, $date_end = null, $column = 'date_req')
    {
        if (!empty($date_start) && !empty($date_end)) {
            $query->whereBetween($column, [$date_start, $date_end]);
        } elseif (!empty($date_start)) {
            $query->whereDate($column, '>=', $date_start);
        } elseif (!empty($date_end)) {
            $query->whereDate($column, '<=', $date_end);
        }
        return $query;
    }

    public function getAllDataWithDefault(
        $where = array(),
        $per_page = 10,
        $offset = 1,
        $sort_column,
        $sort_order = "ASC",
        $modelWith = [],
        $date_start = null,
        $date_end = null
    ) {
        $query = $this->model->with($modelWith)->where($where);

        $query = $this->applyDateRange($query, $date_start, $date_end);

        $data = $query->offset($offset)
            ->limit($per_page)
            ->orderBy($sort_column, $sort_order)
            ->paginate($per_page);

        return $data;
    }

    public function searchDataMultiColumn(
        $where = [],
        $per_page = 10,
        array $search_columns = [],
        $keyword = "",
        $modelWith = [],
        $date_start = null,
        $date_end = null
    ) {
        $query = $this->model->with($modelWith)->where($where);

        // Apply filter date range
        $query = $this->applyDateRange($query, $date_start, $date_end);

        // Eksekusi pencarian hanya jika keyword dan kolom pencarian tidak kosong
        if (!empty($keyword) && !empty($search_columns)) {
            $query->where(function ($q) use ($search_columns, $keyword) {

                $isDateKeyword = (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $keyword);

                foreach ($search_columns as $key => $value) {

                    if (is_array($value)) {
                        $relationName = $key;
                        $relationColumns = $value;

                        $q->orWhereHas($relationName, function ($relQuery) use ($relationColumns, $keyword) {
                            $relQuery->where(function ($subQ) use ($relationColumns, $keyword) {
                                foreach ($relationColumns as $relCol) {
                                    $subQ->orWhere($relCol, 'LIKE', '%' . $keyword . '%');
                                }
                            });
                        });
                    } else {
                        $colName = is_string($key) ? $key : $value;
                        $colType = is_string($key) ? strtolower($value) : 'string';

                        if ($colType === 'date' || $colType === 'datetime') {
                            if ($isDateKeyword) {
                                $q->orWhereDate($colName, $keyword);
                            }
                        } else {
                            $q->orWhere($colName, 'LIKE', '%' . $keyword . '%');
                        }
                    }
                }
            });
        }

        $per_page = $per_page > 0 ? $per_page : $query->count();

        return $query->paginate($per_page);
    }
}