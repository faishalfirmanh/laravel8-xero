<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumRandomuuid extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('paket_umrohs', function (Blueprint $table) {
            //
            $table->text('random_uuid_paket')->nullable()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('paket_umrohs', function (Blueprint $table) {
            //
            $table->dropColumn('random_uuid_paket');
        });
    }
}
