<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnBaseForCurrency extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('p_bills', function (Blueprint $table) {
            $table->decimal('total_base', 19, 4)->default(0);
            $table->decimal('subtotal_base', 19, 4)->default(0);
            $table->decimal('tax_base', 19, 4)->default(0);
            $table->decimal('nominal_paid_base', 19, 4)->default(0);
            $table->decimal('nominal_due_base', 19, 4)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('p_bills', function (Blueprint $table) {
            //
        });
    }
}
