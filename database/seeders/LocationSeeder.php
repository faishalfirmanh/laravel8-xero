<?php

namespace Database\Seeders;

use App\Models\MasterData\Location\City;
use App\Models\MasterData\Location\Province;
use App\Models\MasterData\Location\Subdistrict;
use App\Models\MasterData\Location\Village;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $path = database_path('../Sql_location/profinsi.sql');
        $path2 = database_path('../Sql_location/city.sql');
        $path3 = database_path('../Sql_location/district.sql');
        $path4 = database_path('../Sql_location/vilages.sql');
        $path5 = database_path('../Sql_location/indexing.sql');

        // Province::query()->delete();
        // Subdistrict::query()->delete();
        // City::query()->delete();
        // Villages::query()->delete();

        // if ($prov < 1 && $kec < 1 && $city < 1 && $village < 1) {
            if (File::exists($path) && File::exists($path2) && File::exists($path3) && File::exists($path4)) {

                DB::beginTransaction();
                try {
                    $sql = File::get($path);
                    $sql2 = File::get($path2);
                    $sql3 = File::get($path3);
                    $sql4 = File::get($path4);
                    //$sql_index = File::get($path5);

                    DB::unprepared($sql);
                    DB::unprepared($sql2);
                    DB::unprepared($sql3);
                    DB::unprepared($sql4);
                   // DB::unprepared($sql_index);
                    DB::commit();
                    $this->command->info('SQL file executed successfully.');
                } catch (\Exception $th) {
                    DB::rollBack();
                    $this->command->error('SQL Error insert.'. $th->getMessage());
                    die();
                }
            } else {
                $this->command->error('SQL file not found.');
            }
        // }else{
        //     $this->command->error('data location is already available');
        // }


    }
}
