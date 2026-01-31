<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentHotelsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_hotels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoices_id')
                  ->constrained('invoices_hotels')
                  ->onDelete('cascade');
            $table->decimal('payment_idr',19, 4)->default(0);
            $table->decimal('payment_sar',19, 4)->default(0);
            $table->integer('created_by')->nullable();
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
        Schema::dropIfExists('payment_hotels');
    }
}
