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
         $list = ['an-namiroh','rihlah saidah','antrav','tajali'];
        foreach ($list as $key => $value) {
            TravelName::firstOrCreate(['name'=>$value],[
                'is_active'=>true,
                'created_by'=>1
            ]);
            $this->command->info('Berhasil menyiapkan travel: ' . $value);
        }

    }
}
