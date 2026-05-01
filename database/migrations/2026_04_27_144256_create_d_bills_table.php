<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDBillsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('d_bills', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bills_parent_id');
            $table->string('item_code')->nullable();
            $table->string('desc')->nullable();
            $table->integer('qty')->nullable();
            $table->decimal('unit_price', 19, 4)->default(0);
            $table->unsignedBigInteger('account_id_coa');
            $table->integer('tax_rate')->nullable();
            $table->string('paket_tracking_uuid')->nullable();
            $table->string('divisi_travel_tracking_uuid')->nullable();
            $table->decimal('amount', 19, 4)->default(0);

            $table->foreign('account_id_coa')
                ->references('id')
                ->on('coas')
                ->onDelete('cascade');

            $table->foreign('bills_parent_id')
                ->references('id')
                ->on('p_bills')
                ->onDelete('cascade');
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
        Schema::dropIfExists('d_bills');
    }
}
