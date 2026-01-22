<?php

namespace Database\Seeders;

use App\Models\MasterData\Hotel;
use Illuminate\Database\Seeder;

class HotelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $list = ['Rafles Makkah Palace', 'Makkah Al Azzizah'];

        foreach ($list as $key => $value) {
            Hotel::updateOrCreate([
                'name' => $value
            ], [
                'name' => $value,
                'type_location_hotel' => 1,
            ]);
        }
    }
}
