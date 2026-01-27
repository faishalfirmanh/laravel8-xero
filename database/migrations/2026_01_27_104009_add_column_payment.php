<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnPayment extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('invoices_hotels', function (Blueprint $table) {
            $table->decimal('final_payment_sar',19, 4)->default(0);
            $table->decimal('final_payment_idr',19, 4)->default(0);
            $table->decimal('less_payment_idr',19, 4)->default(0);
            $table->decimal('less_payment_sar',19, 4)->default(0);
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
            Schema::dropIfExists('final_payment_sar');
            Schema::dropIfExists('final_payment_idr');
            Schema::dropIfExists('less_payment_idr');
            Schema::dropIfExists('less_payment_sar');
        });
    }
}
