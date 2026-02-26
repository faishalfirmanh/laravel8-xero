<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePrepaymentInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('prepayment_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number');
            $table->string('contact_name');
            $table->string('invoice_uuid');
            $table->string('prepayment_uuid');
            $table->date('date');
            $table->decimal('amount',19, 4);
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
        Schema::dropIfExists('prepayment_invoices');
    }
}
