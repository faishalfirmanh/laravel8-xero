<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateItemsPaketAllFromXerosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('items_paket_all_from_xeros', function (Blueprint $table) {
            $table->id();
            $table->string('uuid_proudct_and_service');
            $table->string('code');
            $table->string('nama_paket');
            $table->string('purchase_AccountCode')->nullable();
            $table->string('sales_AccountCode')->nullable();
            $table->integer('total_hari')->default(0);
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
        Schema::dropIfExists('items_paket_all_from_xeros');
    }
}
