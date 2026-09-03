<?php
namespace App\Http\Repository\Transaction;

use App\Http\Repository\BaseRepository;

use App\Models\Transaction\TransactionAllCoa;

class TransCoaRepo extends BaseRepository
{
    public function __construct(TransactionAllCoa $model)
    {
        $this->model = $model;
    }

    public function wherenDataIn($column, $value)
    {
        return $this->model->whereIn($column, $value);
    }

    public function searchDataMultiColumn($where = [], $per_page = 10, array $search_columns = [], $keyword = "", $modelWith = [], array $dateRange = [])
    {
        $query = $this->model->with($modelWith)->where($where);

        // ── DATE RANGE FILTER ──
        if (!empty($dateRange['column'])) {
            if (!empty($dateRange['start'])) {
                $query->whereDate($dateRange['column'], '>=', $dateRange['start']);
            }
            if (!empty($dateRange['end'])) {
                $query->whereDate($dateRange['column'], '<=', $dateRange['end']);
            }
        }

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

    public function getAllDataWithDefault($where = array(), $per_page = 10, $offset = 1, $sort_column, $sort_order = "ASC", $modelWith = [], array $dateRange = [])
    {
        $query = $this->model->with($modelWith)->where($where);

        // ── DATE RANGE FILTER ──
        if (!empty($dateRange['column'])) {
            if (!empty($dateRange['start'])) {
                $query->whereDate($dateRange['column'], '>=', $dateRange['start']);
            }
            if (!empty($dateRange['end'])) {
                $query->whereDate($dateRange['column'], '<=', $dateRange['end']);
            }
        }

        $data = $query->offset($offset)->limit($per_page)->orderBy($sort_column, $sort_order)->paginate($per_page);

        return $data;
    }
}
