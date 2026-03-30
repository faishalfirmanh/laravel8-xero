<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MasterData\MasterRoleUser;
class MasterRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

      $list_role = ['dokument','cs','admin','keuangan','it','accounting'];

      foreach ($list_role as $key => $value) {
            MasterRoleUser::updateOrCreate([
                'nama_role' => $value
            ], [
                'is_active' => 1,
                'created_by' => 1,
                'guard_name'=>'api'
            ]);
            $this->command->info('berhasil insert data. '.$value);
      }


    }
}
