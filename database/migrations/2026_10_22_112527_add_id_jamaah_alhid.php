<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdJamaahAlhid extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('paket_umroh_jamaahs', function (Blueprint $table) {
            Schema::table('paket_umroh_jamaahs', function (Blueprint $table) {
                $table->bigInteger('id_jamaah_alhid')->nullable();
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
        Schema::table('paket_umroh_jamaahs', function (Blueprint $table) {
            Schema::dropIfExists('id_jamaah_alhid');
        });
    }
}
