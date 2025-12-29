<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexToLocationTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('location_tables', function (Blueprint $table) {

            Schema::table('location_provinces', function (Blueprint $table) {
                 $table->index('name', 'index_name_prov');
            });

            Schema::table('location_city', function (Blueprint $table) {
                $table->index('name', 'index_name_city');
            });

            Schema::table('location_villages', function (Blueprint $table) {
                $table->index('name', 'index_name_village');
            });

            Schema::table('location_districts', function (Blueprint $table) {
                $table->index('name', 'index_name_diss');
            });
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('location_tables', function (Blueprint $table) {
            Schema::table('location_provinces', function (Blueprint $table) {
                $table->dropIndex('index_name_prov');
            });
            Schema::table('location_regencies', function (Blueprint $table) {
                $table->dropIndex('index_name_regency');
            });
            Schema::table('location_villages', function (Blueprint $table) {
                $table->dropIndex('index_name_village');
            });
            Schema::table('location_districts', function (Blueprint $table) {
                $table->dropIndex('index_name_diss');
            });
        });
    }
}
