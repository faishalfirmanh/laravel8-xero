<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Config\ConfigCurrency;

class ConfigCurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
       $cek = ConfigCurrency::count();
       if($cek == 0){
         ConfigCurrency::create(['nominal_rupiah_1_riyal'=>4300]);
       }
    }
}
