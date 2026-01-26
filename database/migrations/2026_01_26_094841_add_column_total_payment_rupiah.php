<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnTotalPaymentRupiah extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('invoices_hotels', function (Blueprint $table) {
            //
            $table->decimal('total_payment_rupiah',19, 4)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('invoices_hotels', function (Blueprint $table) {
            //
            Schema::dropIfExists('total_payment_rupiah');
        });
    }
}
