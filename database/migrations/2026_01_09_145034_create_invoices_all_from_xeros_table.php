<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoicesAllFromXerosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('invoices_all_from_xeros', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number');
            $table->string('invoice_uuid');
            $table->decimal('invoice_amount')->default(0);
            $table->decimal('spending_amount')->default(0);
            $table->decimal('profit_amount')->default(0);
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
        Schema::dropIfExists('invoices_all_from_xeros');
    }
}
