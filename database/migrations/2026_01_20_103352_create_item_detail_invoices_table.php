<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateItemDetailInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('item_detail_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number');
            $table->string('uuid_invoices');
            $table->string('uuid_item');
            $table->integer('qty')->default(0);
            $table->decimal('unit_price',19, 4)->default(0);
            $table->decimal('total_amount_each_row',19, 4)->default(0);
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
        Schema::dropIfExists('item_detail_invoices');
    }
}
