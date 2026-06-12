<?php

namespace Database\Seeders;

use App\Models\ItemsPaketAllFromXero;
use App\Models\MasterData\BankXero;
use App\Models\MasterData\Coa;
use App\Models\MasterData\TrackingCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
class ResetMasterTransSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        Schema::disableForeignKeyConstraints();

        // 2. Truncate tabel (menghapus data & reset primary key ke 1)
        BankXero::truncate();
        ItemsPaketAllFromXero::truncate();
        Coa::truncate();
        TrackingCategory::truncate();
        // 3. Aktifkan kembali foreign key checks
        Schema::enableForeignKeyConstraints();

        $this->command->info('data MASTER transaction berhasil dihapus dan primary key telah di-reset ke 1!');
    }
}
