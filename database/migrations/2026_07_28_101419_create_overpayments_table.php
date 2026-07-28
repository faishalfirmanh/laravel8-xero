<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOverpaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('overpayments', function (Blueprint $table) {
            $table->id();
            $table->decimal('nominal_overpayment', 19, 4)->default(0);
            $table->bigInteger('invoice_id')->nullable();
            $table->bigInteger('bills_id')->nullable();
            $table->bigInteger('bank_id')->nullable();
            $table->bigInteger('trans_bank_id')->nullable();
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
        Schema::dropIfExists('overpayments');
    }
}
