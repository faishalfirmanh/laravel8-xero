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
->when($search, function($q) use($search){
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
}
