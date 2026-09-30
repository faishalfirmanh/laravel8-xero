<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductUomsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_uoms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->foreign('item_id')
                ->references('id')
                ->on('items_paket_all_from_xeros')
                ->onDelete('cascade');

            $table->unsignedBigInteger('unit_satuan_id')->nullable();
            $table->foreign('unit_satuan_id')
                ->references('id')
                ->on('units')
                ->onDelete('cascade');

            $table->integer('conversion_factor');
            $table->decimal('sell_price', 19, 4)->default(1);
            $table->unique(['item_id', 'unit_satuan_id']);
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
        Schema::dropIfExists('product_uoms');
    }
}
