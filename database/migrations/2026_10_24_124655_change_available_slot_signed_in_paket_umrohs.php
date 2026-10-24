<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class ChangeAvailableSlotSignedInPaketUmrohs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("
            ALTER TABLE paket_umrohs
            MODIFY available_slot INT NOT NULL DEFAULT 0
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("
            ALTER TABLE paket_umrohs
            MODIFY available_slot INT NOT NULL DEFAULT 0
        ");
    }
}
