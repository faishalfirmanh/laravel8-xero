<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumCoaId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('item_detail_invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('coa_id')->nullable();
            $table->foreign('coa_id')
                ->references('id')
                ->on('coas')
                ->onDelete('cascade');
            $table->unsignedBigInteger('parent_inv_id')->nullable();
            $table->foreign('parent_inv_id')
                ->references('id')
                ->on('invoices_all_from_xeros')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('item_detail_invoices', function (Blueprint $table) {
            //
        });
    }
}
