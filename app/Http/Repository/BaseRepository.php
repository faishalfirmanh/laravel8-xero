<?php

namespace App\Http\Repository;
use DB;
use Illuminate\Database\Eloquent\Model;
class BaseRepository
{

    public $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function find($id)
    {
        return $this->model->find($id);
    }

    public function WhereDataWith($modelWith, $where)
    {
        return $this->model->with($modelWith)->where($where);
    }

    public function getAllDataNoLimit()
    {
        return $this->model->get();
    }

    public function findOrfail($id)
    {
        return $this->model->findOrfail($id);
    }

    public function CreateOrUpdate(array $attributes, $id = null)
    {
        DB::beginTransaction();
        try {
            date_default_timezone_set('Asia/Jakarta');
            if ($id > 0) {
                $data = $this->model->find($id);
                if ($id && !$data)
                    return "no data id " . $id;
                $data->fill($attributes);
                $data->save();
            } else {
                $data = $this->model->create($attributes);
            }
            DB::commit();
            return $data;
        } catch (Exception $e) {
            DB::rollBack();
            return "error saved";
        }
    }

    public function UpdateMultipleRow($column_string, $array_id, array $value_updated)
    {
        date_default_timezone_set('Asia/Jakarta');
        $data = $this->model->whereIn($column_string, $array_id)->update($value_updated);
        return $data;
    }

    public function UpdateDinamisColumn($where, array $attributes)
    {
        DB::beginTransaction();
        try {
            date_default_timezone_set('Asia/Jakarta');
            $data = $this->model->where($where)->first();
            $data->fill($attributes);
            $save = $data->save();
            DB::commit();
            return $save;
        } catch (Exception $e) {
            DB::rollBack();
            return "error saved";
        }
    }

    public function delete($id)
    {
        DB::beginTransaction();
        try {
            $data = $this->model->find($id);
            $save = $data->delete();
            DB::commit();
            return $save;
        } catch (Exception $e) {
            DB::rollBack();
            return "failed deleted";
        }
    }

    public function deleteWithIdDinamis($column, $id) //deleted single
    {
        DB::beginTransaction();
        try {
            $data = $this->model->where($column, $id)->first();
            $save = $data->delete();
            DB::commit();
            return $save;
        } catch (Exception $e) {
            DB::rollBack();
            return "failed deleted";
        }
    }

    public function deleteWithIdDinamisMultiRow($column, $id)//delete multiple
    {
        $data = $this->model->where($column, $id);
        $save = $data->delete();
        return $save;
    }

    public function getDataPaginate($kolom_name, $limit, $keyword = null)
    {
        return $this->model
            ->when(!empty($keyword), function ($q) use ($kolom_name, $keyword) {
                $q->where($kolom_name, 'like', '%' . $keyword . '%');
            })
            ->paginate($limit);
    }

    //semua paginate / list menggunakan ini
    public function getAllDataWithDefault($where = array(), $per_page = 10, $offset = 1, $sort_column, $sort_order = "ASC")
    {
        $data = $this->model->where($where)->offset($offset)->limit($per_page)->orderBy($sort_column, $sort_order)->paginate($per_page);
        return $data;
    }

    // semua paginate / list yang ada searchnya. kalau tanpa relasi dan cuma 1 kolom
    public function searchData($where = array(), $per_page = 10, $offset = 1, $search_column = "", $keyword = "")
    {
        $data = $this->model->where($where)->offset($offset)->limit($per_page)->whereRaw("LOWER($search_column) like '%" . $keyword . "%'")->paginate($per_page);
        return $data;
    }

    public function countData($where = array())
    {
        $data = $this->model->where($where)->count();
        return $data;
    }

    public function whereData($where = array())
    {
        $data = $this->model->where($where);
        return $data;
    }

    public function sumDataWhereDinamis($where = array(), string $kolom)
    {
        $data = $this->model->where($where)->sum($kolom);
        return $data;
    }

    public function getLastIdTable(string $column_id)
    {
        return $this->model->max($column_id);
    }
}
