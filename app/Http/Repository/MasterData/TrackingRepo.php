<?php

namespace App\Http\Repository\MasterData;

use App\Http\Repository\BaseRepository;
use App\Models\MasterData\TrackingCategory;
use DB;

class TrackingRepo extends BaseRepository
{
    public function __construct(TrackingCategory $model)
    {
        $this->model = $model;
    }

    public function getLastIdPlusOne()
    {
        $no = $this->model->latest()->first() == null ? 1 : $this->model->latest()->first()->id + 1;
        return $no;
    }

    public function getUUIDKategory($parent, array $uuidKategory = [])
    {
        if (empty($uuidKategory)) {
            return collect();
        }

        $placeholders = implode(
            ',',
            array_fill(0, count($uuidKategory), '?')
        );

        $sql = "
        SELECT
            items.item_name_category
        FROM tracking_categories tc
        JOIN JSON_TABLE(
            tc.lines_category,
            '$[*]' COLUMNS (
                item_uuid_category VARCHAR(255)
                    PATH '$.item_uuid_category',
                item_name_category VARCHAR(255)
                    PATH '$.item_name_category'
            )
        ) AS items
        WHERE tc.name_parent_category = ?
        AND items.item_uuid_category IN ($placeholders)
    ";

        $bindings = array_merge(
            [$parent],
            $uuidKategory
        );

        return collect(
            DB::select($sql, $bindings)
        );
    }
}
