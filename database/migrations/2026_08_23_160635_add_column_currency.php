<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnCurrency extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transaction_all_coas', function (Blueprint $table) {
            //
            $table->string('code_curr', 10)->nullable();//code currencty
            $table->decimal('nominal_currency', 19, 4)->default(1);
            $table->decimal('base_nominal', 19, 4)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transaction_all_coas', function (Blueprint $table) {
            //
        });
    }
}
