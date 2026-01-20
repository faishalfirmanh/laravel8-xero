<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MasterData\MasterPengeluaranPaket;
class SeederMasterPengeluaran extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $list = ['tiket pesawat','day_kurs','mak harga makkah','mad harga madinah','n guide',
        'baksis', 'visa','handling dan op','handling indo','asuransi','koper album','tiket pesawat'];


        foreach ($list as $key => $value) {
            MasterPengeluaranPaket::updateOrCreate([
                'nama_pengeluaran'=>  strtolower($value)
            ],[
                'nama_pengeluaran'=>  strtolower($value),
                'is_active'=>true
            ]);
            $this->command->info('sukses seeder master pengeluaran '.$key);
        }

    }
}
