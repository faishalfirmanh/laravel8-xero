<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\ConfigCurrencySeeder;
use Database\Seeders\SeederMasterPengeluaran;
use Database\Seeders\HotelSeeder;
use Database\Seeders\UserRegisterSeeder;
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // User::factory(10)->create();
          $this->call([
                ConfigCurrencySeeder::class,
                SeederMasterPengeluaran::class,
                HotelSeeder::class,
                UserRegisterSeeder::class
          ]);
    }
}
