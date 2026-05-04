<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MasterData\TrackingCategory;


class TrackingCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //


$data = [
            [
                'name_parent_category' => 'nama paket',
                'lines_category' => [
                    [
                        "id_parent" => "1",
                        "item_name_category" => "paket 12 hari 12 mei 2026 30 pax",
                        "item_uuid_category" => "7459"
                    ],
                    [
                        "id_parent" => "1",
                        "item_name_category" => "paket 10 hari 30 mei 2026 15 pax",
                        "item_uuid_category" => "8139"
                    ],
                    // Add more items here if needed
                ],
                'created_by' => 1,
            ],
            [
                'name_parent_category' => 'divisi',
                'lines_category' => [
                    [
                        "id_parent" => 2,
                        "item_name_category" => "paket antrav",
                        "item_uuid_category" => "0483"
                    ],
                    [
                        "id_parent" => 2,
                        "item_name_category" => "paket rihlah",
                        "item_uuid_category" => "7493"
                    ],
                    [
                        "id_parent" => 2,
                        "item_name_category" => "pake annamiroh",
                        "item_uuid_category" => "94"
                    ],
                    // Add more items here if needed
                ],
                'created_by' => 1,
            ],
        ];

        foreach ($data as $item) {
            TrackingCategory::updateOrCreate(
                ['name_parent_category' => $item['name_parent_category']], // Unique condition
                [
                    'lines_category' => json_encode($item['lines_category']), // Ensure it's stored as JSON
                    'created_by' => $item['created_by'],
                    // 'updated_by' => 1, // uncomment if you have this column
                ]
            );
        }

    }
}
