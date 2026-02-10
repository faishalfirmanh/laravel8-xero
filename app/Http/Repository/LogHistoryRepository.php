<?php

namespace App\Http\Repository;

use App\Http\Repository\BaseRepository;
use App\Models\LogHistory;

class LogHistoryRepository extends BaseRepository{

    public function __construct(LogHistory $model)
    {
        $this->model = $model;
    }

    public function store(array $payload)
    {
        return $this->model->create($payload);
    }
}
