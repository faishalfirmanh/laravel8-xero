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

    public function searchDataMultiColumn($where = [], $per_page = 10, array $search_columns = [], $keyword = "", $modelWith = [])
    {
        $query = $this->model->with($modelWith)->where($where);

        // Eksekusi pencarian hanya jika keyword dan kolom pencarian tidak kosong
        if (!empty($keyword) && !empty($search_columns)) {
            $query->where(function ($q) use ($search_columns, $keyword) {

                // Cek apakah keyword formatnya menyerupai tanggal (YYYY-MM-DD)
                $isDateKeyword = (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $keyword);

                foreach ($search_columns as $key => $value) {

                    // 1. CEK JIKA VALUE ADALAH ARRAY (PENCARIAN RELASI)
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
                    }
                    // 2. JIKA VALUE BUKAN ARRAY (PENCARIAN TABEL UTAMA)
                    else {
                        // Deteksi nama kolom dan tipenya
                        $colName = is_string($key) ? $key : $value;
                        $colType = is_string($key) ? strtolower($value) : 'string';

                        if ($colType === 'date' || $colType === 'datetime') {
                            // Pencarian khusus kolom Date
                            if ($isDateKeyword) {
                                $q->orWhereDate($colName, $keyword);
                            }
                        } else {
                            // Pencarian Text Normal
                            $q->orWhere($colName, 'LIKE', '%' . $keyword . '%');
                        }
                    }

                }
            });
        }

        // Hindari error saat paginate diberi limit -1 atau nilai tidak valid dari Datatable
        $per_page = $per_page > 0 ? $per_page : $query->count();

        return $query->paginate($per_page);
    }
}
