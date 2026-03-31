<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MasterData\BusinessLine;
class BusinessLineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        $list = ['spbu','travel','mbg','mini-market','kos-kosan'];
        foreach ($list as $key => $value) {
            BusinessLine::firstOrCreate(['name_business'=>$value],[
                'is_active'=>true,
                'created_by'=>1
            ]);
            $this->command->info('Berhasil menyiapkan lini usaha : ' . $value);
        }
    }
}
