<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFeeOverpaysTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fee_overpays', function (Blueprint $table) {
            $table->id();
            $table->date('date_transaction');

            $table->unsignedBigInteger('invoice_id');
            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices_all_from_xeros')
                ->onDelete('cascade');

            $table->unsignedBigInteger('bank_id_origin');
            $table->foreign('bank_id_origin')
                ->references('id')
                ->on('bank_xeros')
                ->onDelete('cascade');

            $table->decimal('nominal', 19, 4)->default(1);

            $table->string('bank_tujuan_jamaah_rek');
            $table->string('reference')->nullable();
            $table->unsignedBigInteger('account_id');//AMBIL coa diskon
            $table->foreign('account_id')
                ->references('id')
                ->on('coas')
                ->onDelete('cascade');

            $table->unsignedBigInteger('overpay_id');
            $table->foreign('overpay_id')
                ->references('id')
                ->on('overpayments')
                ->onDelete('cascade');

            $table->bigInteger('jamaah_id')->nullable();
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
        Schema::dropIfExists('fee_overpays');
    }
}
