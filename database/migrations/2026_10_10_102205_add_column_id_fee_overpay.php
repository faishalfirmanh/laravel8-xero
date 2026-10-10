<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnIdFeeOverpay extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transaction_nominal_bank_accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('id_fee_overpay')->nullable();
            $table->foreign('id_fee_overpay')
                ->references('id')
                ->on('fee_overpays')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transaction_nominal_bank_accounts', function (Blueprint $table) {
            //
        });
    }
}
