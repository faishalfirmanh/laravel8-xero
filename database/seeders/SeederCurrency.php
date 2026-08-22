<?php

namespace Database\Seeders;

use App\Models\MasterData\MasterCurrency;
use Illuminate\Database\Seeder;

class SeederCurrency extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        $code = ['IDR', 'SAR', 'USD'];
        $nominal = [1, 4800, 18000];
        foreach ($code as $key => $value) {
            MasterCurrency::updateOrCreate([
                'code_curr' => $value
            ], [
                'satu_rupiah' => 1,
                'nominal_currency' => $nominal[$key],
            ]);
        }

    }
}
