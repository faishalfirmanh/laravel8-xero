<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnidTransBankTransfer extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transaction_nominal_bank_accounts', function (Blueprint $table) {
            $table->foreignId('trans_transfer_bank_id')
                ->constrained('transaction_transfer_banks')
                ->cascadeOnDelete()
                ->nullable();
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
            Schema::dropIfExists('trans_transfer_bank_id');
        });
    }
}
