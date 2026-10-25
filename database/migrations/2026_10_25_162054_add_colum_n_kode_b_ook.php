<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumNKodeBOok extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('paket_umroh_jamaahs', function (Blueprint $table) {
            // unique: kode_booking yang sama = booking yang sama (callback bisa dikirim ulang)
            $table->string('kode_booking', 100)->nullable()->unique()->after('id_jamaah_alhid');
            $table->date('tanggal_booked')->nullable()->after('kode_booking');
            // 'internal' = diinput admin, 'halalgo' = dari callback Halal Go
            $table->string('source', 20)->default('internal')->after('tanggal_booked');
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
            $table->dropUnique(['kode_booking']);
            $table->dropColumn(['kode_booking', 'tanggal_booked', 'source']);
        });
    }
}
