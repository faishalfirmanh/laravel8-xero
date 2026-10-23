<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumDate extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('items_paket_all_from_xeros', 'departure_date')) {
            Schema::table('items_paket_all_from_xeros', function (Blueprint $table) {
                $table->date('departure_date')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('items_paket_all_from_xeros', function (Blueprint $table) {
            $table->dropColumn('departure_date');
        });
    }
}
