<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnNominalSpend extends Migration
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
            $table->renameColumn('nominal', 'nominal_receive');
            $table->decimal('nominal_spend', 19, 4)->default(0);
            $table->decimal('nominal_transfer', 19, 4)->default(0);
            $table->date('date_transaction');
            $table->string('reference_detail')->nullable();
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
