<?php

namespace Database\Seeders;

use App\Models\Config\RoleMenus;
use App\Models\Config\RoleUsers;
use App\Models\MasterData\Menu;
use DB;
use Illuminate\Database\Seeder;

class ResetMenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::beginTransaction();
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            RoleMenus::truncate();
            RoleUsers::truncate();
            Menu::truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            DB::commit();
            $this->command->info('Role berhasil di hapus');
        } catch (\Throwable $th) {
            //throw $th;
            $this->command->error('SQL Error hapus.' . $th->getMessage());
            DB::rollBack();
        }
    }
}
