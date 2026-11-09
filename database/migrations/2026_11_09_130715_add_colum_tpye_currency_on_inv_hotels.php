<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumTpyeCurrencyOnInvHotels extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('invoices_hotels', function (Blueprint $table) {
            //
            $table->string('type_currency', 3)->default('SAR');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('invoices_hotels', function (Blueprint $table) {
            $table->dropColumn('type_currency');
        });
    }
}
