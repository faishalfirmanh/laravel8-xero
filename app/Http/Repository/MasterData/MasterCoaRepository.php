<?php

namespace App\Http\Repository\MasterData;

use App\Models\MasterData\MasterCoa;
use App\Http\Repository\BaseRepository;


class MasterCoaRepository extends BaseRepository
{
    public function __construct(MasterCoa $model)
    {
        parent::__construct($model);
    }
    public function getDataDataTable(
        string $orderBy,
        int $limit,
        int $page,
        ?string $search = null
    ): array {
    $query = $this->model
        ->when($search, function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('code', 'like', "%{$search}%");
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
public function syncFromXero(array $accounts, array $taxRateMap, array $ytdMap)
{
    foreach ($accounts as $account) {

        $taxRate = $taxRateMap[$account['TaxType'] ?? ''] ?? 0;
        $ytd     = $ytdMap[$account['Name'] ?? ''] ?? 0;

        $this->model->updateOrCreate(
            [
                'xero_account_id' => $account['AccountID'],
            ],
            [
                'code'        => $account['Code'] ?? null,
                'name'        => $account['Name'] ?? null,
                'description' => $account['Description'] ?? null,
                'type'        => $account['Type'] ?? null,
                'tax_type'    => $account['TaxType'] ?? null,
                'tax_rate'    => $taxRate,
                'ytd'         => $ytd,
            ]
        );
    }

    return $this->model->orderBy('code')->get();
}
}
