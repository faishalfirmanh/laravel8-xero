<?php

namespace App\Http\Repository\MasterData;

use App\Http\Repository\BaseRepository;
use App\Models\MasterData\MasterRoleUser;

class RoleUserRepository extends BaseRepository
{
    public function __construct(MasterRoleUser $model)
    {
        $this->model = $model;
    }

    public function getDataDataTable(
        string $orderBy,
        int $limit,
        int $page,
        ?string $search = null
    ): array {
        $query = $this->model
            ->when($search, function ($q) use ($search) {
                $q->where('nama_role', 'like', "%{$search}%");
            });
        return [
            'total' => $query->count(),
            'data' => $query
                ->orderBy($orderBy)
                ->skip(($page - 1) * $limit)
                ->take($limit)
                ->get(),
        ];
    }



    public function getAllDataWithDefault($where = array(), $per_page = 10, $offset = 1, $sort_column, $sort_order = "ASC")
    {
        $data = $this->model->with('menus')->where($where)->offset($offset)->limit($per_page)->orderBy($sort_column, $sort_order)->paginate($per_page);
        return $data;
    }

    // semua paginate / list yang ada searchnya. kalau tanpa relasi dan cuma 1 kolom
    public function searchData($where = array(), $per_page = 10, $offset = 1, $search_column = "", $keyword = "")
    {
        $data = $this->model->with('menus')->where($where)->offset($offset)->limit($per_page)->whereRaw("LOWER($search_column) like '%" . $keyword . "%'")->paginate($per_page);
        return $data;
    }
}
