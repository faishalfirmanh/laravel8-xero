<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoicePriceGapsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('invoice_price_gaps', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number');
            $table->string('invoice_uuid');
            $table->string('contact_name');
            $table->decimal('total_nominal_payment_xero',19, 4);
            $table->decimal('total_nominal_payment_local',19, 4);
            $table->decimal('total_price_return',19, 4)->default(0);
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
        Schema::dropIfExists('invoice_price_gaps');
    }
}
