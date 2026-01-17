<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnJenisPaket extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('items_paket_all_from_xeros', function (Blueprint $table) {
            $table->tinyInteger('jenis_item')->default(1);
            $table->decimal('price_purchase',19, 4)->default(0);
            $table->decimal('price_sales',19, 4)->default(0);
            $table->string('desc')->nullable();
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
            //
        });
    }
}
