<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumIdParentBankTrans extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transaction_nominal_bank_accounts', function (Blueprint $table) {
            //
            $table->unsignedBigInteger('id_parent_bank')->nullable();
            $table->foreign('id_parent_bank')
                ->references('id')
                ->on('transaction_bank_trans_p_s')
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
            Schema::dropIfExists('id_parent_bank');
        });
    }
}
