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

    /**
     * Merge option tracking dari Xero ke lines_category tanpa replace data lama.
     * Return null jika parent tidak ada di DB (baris static, tidak dibuat otomatis).
     */
    public function mergeLinesFromXero($parentName, array $xeroOptions, $dryRun = false)
    {
        return DB::transaction(function () use ($parentName, $xeroOptions, $dryRun) {
            $row = $this->model->newQuery()
                ->whereRaw('LOWER(TRIM(name_parent_category)) = ?', [mb_strtolower(trim($parentName))])
                ->lockForUpdate()
                ->first();

            if (!$row) {
                return null;
            }

            $lines = is_array($row->lines_category) ? $row->lines_category : [];

            // uuid (lowercase) => nama yang sudah ada di DB
            $existing = [];
            foreach ($lines as $line) {
                if (!empty($line['item_uuid_category'])) {
                    $existing[strtolower($line['item_uuid_category'])] = isset($line['item_name_category'])
                        ? $line['item_name_category']
                        : '';
                }
            }

            $added = [];
            $nameDiff = [];

            foreach ($xeroOptions as $opt) {
                $rawUuid = isset($opt['TrackingOptionID']) ? trim($opt['TrackingOptionID']) : '';
                $name = isset($opt['Name']) ? trim($opt['Name']) : '';
                $key = strtolower($rawUuid);

                if ($rawUuid === '' || $name === '') {
                    continue;
                }

                // Sudah ada -> jangan diubah, hanya dilaporkan kalau namanya beda
                if (array_key_exists($key, $existing)) {
                    if ($existing[$key] !== $name) {
                        $nameDiff[] = [
                            'uuid' => $rawUuid,
                            'db' => $existing[$key],
                            'xero' => $name,
                        ];
                    }
                    continue;
                }

                $newLine = [
                    'id_parent' => $row->id,
                    'item_name_category' => $name,
                    'item_uuid_category' => $rawUuid,
                ];

                $lines[] = $newLine;
                $existing[$key] = $name;
                $added[] = $newLine;
            }

            if (!$dryRun && !empty($added)) {
                $row->lines_category = array_values($lines);
                $row->save();
            }

            return [
                'id_parent' => $row->id,
                'parent' => $row->name_parent_category,
                'total_xero' => count($xeroOptions),
                'added_count' => count($added),
                'added' => $added,
                'name_different' => $nameDiff, // tidak ditimpa, hanya info
            ];
        });
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
