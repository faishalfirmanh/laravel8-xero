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
        try {
            $tables = [
                (new RoleMenus)->getTable(),
                (new RoleUsers)->getTable(),
                (new Menu)->getTable(),
            ];

            DB::statement('SET FOREIGN_KEY_CHECKS=0;');

            foreach ($tables as $table) {
                DB::statement("TRUNCATE TABLE `{$table}`;");
            }

            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            $this->command->info('Role berhasil di hapus');

        } catch (\Throwable $th) {
            // Pastikan FK checks kembali aktif
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            $this->command->error('SQL Error hapus. ' . $th->getMessage());
            throw $th;
        }
    }
}
