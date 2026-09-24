<?php

namespace Database\Seeders;

use App\Models\MasterData\Toko\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //

        $list_unit = ['PCS', 'ROLL', 'KG', 'LUSIN', 'GROSIR', 'KARTON'];
        foreach ($list_unit as $key => $value) {
            Unit::firstOrCreate(['name' => $value, 'name']);
            $this->command->info('Berhasil menyiapkan unit : ' . $value);
        }
    }
}
