<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnJamaahIdAlhidRelation extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('data_jamaah_xeros', function (Blueprint $table) {
            $table->unsignedBigInteger('id_jamaah_alhid')
                ->nullable()
                ->after('id');
            $table->index('id_jamaah_alhid');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('data_jamaah_xeros', function (Blueprint $table) {
            //
        });
    }
}
