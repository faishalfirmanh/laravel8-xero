<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionBankTransPSTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transaction_bank_trans_p_s', function (Blueprint $table) {
            $table->id();
            $table->string('uuid_to');//contact
            $table->date('date_h');
            $table->string('reference')->nullable();
            $table->tinyInteger('amounts_are')->default(0);//tax exclude = 2, tax inclusive = 1, no tax = 0
            $table->integer('created_by')->nullable();
            $table->decimal('tax', 10, 4)->default(0);
            $table->decimal('subtotal', 19, 4)->default(0);
            $table->decimal('total', 19, 4)->default(0);
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
        Schema::dropIfExists('transaction_bank_trans_p_s');
    }
}
