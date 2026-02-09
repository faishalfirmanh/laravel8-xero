<?php

namespace App\Http\Repository\MasterData;

use App\Http\Repository\BaseRepository;
use App\Models\MasterData\MasterMaskapai;

class MaskapaiRepository extends BaseRepository
{
    public function __construct(MasterMaskapai $model)
    {
        $this->model = $model;
    }

    /**
     * Get data for DataTables with custom pagination
     */
    public function getDataDataTable(
        string $orderBy,
        int $limit,
        int $page,
        ?string $search = null
    ): array {
        $query = $this->model
            ->when($search, function ($q) use ($search) {
                $q->where('nama_maskapai', 'like', "%{$search}%");
            });

        return [
            'total' => $query->count(),
            'data'  => $query
                ->orderBy($orderBy)
                ->offset(($page - 1) * $limit)
                ->limit($limit)
                ->get()
        ];
    }
}
