<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaketUmrohsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        if (!Schema::hasTable('paket_umrohs')) {
            Schema::create('paket_umrohs', function (Blueprint $table) {
                $table->id();
                $table->string('tipe', 20);
                $table->unsignedBigInteger('travel_id')->index();
                $table->string('nama_paket', 255);
                $table->date('tgl_berangkat')->index();
                $table->unsignedSmallInteger('jumlah_hari');
                $table->string('maskapai', 100);
                $table->string('keberangkatan', 100);
                $table->string('landing', 100);
                $table->string('kodeflight', 50)->nullable();
                $table->decimal('hrginfant', 15, 2)->default(0);
                $table->decimal('hrgquad', 15, 2)->default(0);
                $table->decimal('hrgtriple', 15, 2)->default(0);
                $table->decimal('hrgdouble', 15, 2)->default(0);
                $table->decimal('hrgperlengkapan', 15, 2)->default(0);
                $table->string('imgpaketumroh', 500)->nullable();
                $table->string('itinerary', 500)->nullable();
                $table->unsignedInteger('max_slot')->default(0);
                $table->unsignedInteger('available_slot')->default(0);
                $table->unsignedInteger('filled_slot')->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('paket_umroh_hotels')) {
            Schema::create('paket_umroh_hotels', function (Blueprint $table) {
                $table->id();
                $table->foreignId('paket_umroh_id')->constrained('paket_umrohs')->onDelete('cascade');
                $table->unsignedBigInteger('hotel_id')->index();
                $table->string('kota', 20);
                $table->string('nama_hotel', 191);
                $table->string('alamat', 255)->nullable();
                $table->unsignedInteger('jarak')->nullable();
                $table->unsignedTinyInteger('bintang')->nullable();
                $table->timestamps();
            });
        }


        // Schema::create('paket_umroh_hotels', function (Blueprint $table) {
        //     $table->id();
        //     $table->foreignId('paket_umroh_id')->constrained('paket_umrohs')->onDelete('cascade');
        //     $table->unsignedBigInteger('hotel_id')->index();
        //     $table->string('kota', 20);
        //     $table->string('nama_hotel', 191);
        //     $table->string('alamat', 255)->nullable();
        //     $table->unsignedInteger('jarak')->nullable();
        //     $table->unsignedTinyInteger('bintang')->nullable();
        //     $table->timestamps();
        // });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('paket_umroh_hotels');
        Schema::dropIfExists('paket_umrohs');
    }
}
