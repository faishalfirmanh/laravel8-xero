<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaketUmrohJamaahsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('paket_umroh_jamaahs')) {
            Schema::create('paket_umroh_jamaahs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('paket_umroh_id');
                $table->unsignedBigInteger('data_jamaah_id');
                $table->unsignedInteger('jumlah_jamaah')->default(1);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['paket_umroh_id', 'data_jamaah_id'], 'uq_paket_jamaah');
                $table->index('data_jamaah_id');

                $table->foreign('paket_umroh_id')
                    ->references('id')->on('paket_umrohs')
                    ->onDelete('cascade');
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
        Schema::dropIfExists('paket_umroh_jamaahs');
    }
}
