<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MasterData\TravelName;
class TravelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        $list = ['an-namiroh', 'rihlah saidah', 'antrav', 'tajali'];
        $path_img = ['assets/img/nam_min.webp', 'assets/img/rih_1.webp', 'assets/img/ant_1.webp', 'assets/img/taj_1.png'];
        $address = ['assets/img/nam_min.webp', 'assets/img/rih_1.webp', 'assets/img/ant_1.webp', 'assets/img/taj_1.png'];
        $name_full = ['assets/img/nam_min.webp', 'assets/img/rih_1.webp', 'assets/img/ant_1.webp', 'assets/img/taj_1.png'];
        foreach ($list as $key => $value) {
            TravelName::firstOrCreate(['name' => $value], [
                'is_active' => true,
                'location_path_image' => $path_img[$key],
                'created_by' => 1
            ]);
            $this->command->info('Berhasil menyiapkan travel: ' . $value);
        }

    }
}
