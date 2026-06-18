<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnPaymentUuuid extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transaction_nominal_bank_accounts', function (Blueprint $table) {
            $table->string('payment_uuid')->nullable()->unique()->after('id');
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
            $table->dropColumn('payment_uuid');
        });
    }
}
