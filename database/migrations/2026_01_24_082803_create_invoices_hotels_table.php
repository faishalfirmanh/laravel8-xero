<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoicesHotelsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('invoices_hotels', function (Blueprint $table) {
            $table->id();
            $table->string('no_invoice_hotel');
            $table->string('uuid_user_order');
            $table->string('nama_pemesan');
            $table->date('check_in');
            $table->date('check_out');
            $table->integer('total_days');
            $table->decimal('total_payment',19, 4)->default(0);
            $table->integer('created_by')->nullable();
            $table->tinyInteger('status')->default(0);
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
        Schema::dropIfExists('invoices_hotels');
    }
}
