<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddItemIdForPaymentToTransactionNominalBankAccounts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        if (!Schema::hasColumn('transaction_nominal_bank_accounts', 'item_id_for_payment')) {
            Schema::table('transaction_nominal_bank_accounts', function (Blueprint $table) {
                $table->unsignedBigInteger('item_id_for_payment')->nullable()->index();
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
        Schema::table('transaction_nominal_bank_accounts', function (Blueprint $table) {
            //
        });
    }
}
