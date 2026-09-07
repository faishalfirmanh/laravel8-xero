<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnJamaahIdAlhidOnPbills extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('p_bills', function (Blueprint $table) {
            //
            $table->unsignedBigInteger('id_jamaah_alhid')
                ->nullable()
                ->after('uuid_from');
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
        Schema::table('p_bills', function (Blueprint $table) {
            $table->dropColumn('id_jamaah_alhid');
        });
    }
}
