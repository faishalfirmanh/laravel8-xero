<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnNumberIdentify extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('data_jamaah_xeros', function (Blueprint $table) {
            $table->string('username')->nullable();
            $table->string('pass')->nullable();
            $table->string('nik')->nullable();
            $table->string('detail_address')->nullable();
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
