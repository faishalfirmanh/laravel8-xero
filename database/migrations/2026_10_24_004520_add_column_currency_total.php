<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnCurrencyTotal extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transaction_bank_trans_p_s', function (Blueprint $table) {
            //
            $table->string('code_currency')->nullable();
            $table->decimal('nominal_currency', 19, 4)->default(1);
            $table->decimal('base_nominal', 19, 4)->default(0);
        });

        Schema::table('transaction_bank_trans_d_s', function (Blueprint $table) {
            $table->decimal('nominal_currency', 19, 4)->default(1);
            $table->decimal('total_base_nominal', 19, 4)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transaction_bank_trans_p_s', function (Blueprint $table) {
            //
        });
    }
}
