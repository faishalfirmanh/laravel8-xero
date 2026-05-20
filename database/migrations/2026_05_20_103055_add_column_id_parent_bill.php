<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnIdParentBill extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transaction_nominal_bank_accounts', function (Blueprint $table) {
            $table->bigInteger('id_parent_bill')->nullable();
            $table->integer('account_transaction')->nullable()->change();
            $table->bigInteger('id_parent_invoice')->nullable();
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
            $table->integer('account_transaction')->nullable(false)->change();
            $table->dropColumn('id_parent_bill');
            $table->dropColumn('id_parent_invoice');
        });
    }
}
