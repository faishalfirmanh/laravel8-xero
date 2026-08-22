<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnTotalbase extends Migration
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
            if (!Schema::hasColumn('nominal_currency', 'transaction_nominal_bank_accounts')) {
                $table->decimal('nominal_currency', 19, 4)->default(1);
            }

            $table->decimal('total_base_spend', 19, 4)->default(0);
            $table->decimal('total_base_receive', 19, 4)->default(0);
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
