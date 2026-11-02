<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnNominalCurrency extends Migration
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
        });
        if (!Schema::hasColumn('invoices_hotels', 'nominal_currency_rp')) {
            Schema::table('invoices_hotels', function (Blueprint $table) {
                $table->decimal('nominal_currency_rp', 10, 4)->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('invoices_hotels', function (Blueprint $table) {
            $table->dropColumn('nominal_currency_rp');
        });
    }
}
