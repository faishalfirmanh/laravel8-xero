<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnIdONItemInvoiceXero extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('items_paket_all_from_xeros', function (Blueprint $table) {
            $table->integer('account_id_purchase')->nullable();
            $table->integer('account_id_salles')->nullable();
            $table->string('desc_salles')->nullable();
            $table->tinyInteger('tax_rate_salles');
            $table->tinyInteger('tax_rate_purchase');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('items_paket_all_from_xeros', function (Blueprint $table) {
            $table->dropColumn('account_id_purchase');
            $table->dropColumn('account_id_salles');
        });
    }
}
