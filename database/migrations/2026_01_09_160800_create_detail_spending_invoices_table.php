<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDetailSpendingInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('detail_spending_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_uuid');
            $table->decimal('nominal');
            $table->unsignedBigInteger('id_master_pengeluaran');
            $table->string('paket_uuid');//product & service
            $table->foreign('id_master_pengeluaran')
                ->references('id')
                ->on('master_pengeluaran_pakets')
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
        Schema::dropIfExists('detail_spending_invoices');
    }
}
