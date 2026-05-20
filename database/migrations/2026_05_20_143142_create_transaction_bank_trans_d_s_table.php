<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionBankTransDSTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transaction_bank_trans_d_s', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trans_bank_parent_id');
            $table->string('item_code')->nullable();
            $table->string('desc')->nullable();
            $table->integer('qty')->nullable();
            $table->decimal('unit_price', 19, 4)->default(0);
            $table->unsignedBigInteger('account_id_coa');
            $table->integer('tax_rate')->nullable();
            $table->string('paket_tracking_uuid')->nullable();
            $table->string('divisi_travel_tracking_uuid')->nullable();
            $table->decimal('amount', 19, 4)->default(0);

            $table->foreign('account_id_coa')
                ->references('id')
                ->on('coas')
                ->onDelete('cascade');

            $table->foreign('trans_bank_parent_id')
                ->references('id')
                ->on('transaction_bank_trans_p_s')
                ->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transaction_bank_trans_d_s');
    }
}
