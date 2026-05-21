<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnIsSpend extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transaction_bank_trans_p_s', function (Blueprint $table) {
            $table->boolean('is_spend')->default(false);
            $table->unsignedBigInteger('bank_id_xero')->nullable();
            $table->foreign('bank_id_xero')
                ->references('id')
                ->on('bank_xeros')
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
        Schema::table('transaction_bank_trans_p_s', function (Blueprint $table) {
            Schema::dropIfExists('is_spend');
        });
    }
}
