<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDataAgensTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('data_agens', function (Blueprint $table) {
            $table->id();
            $table->string('nama_lengkap');
            $table->string('no_ktp')->unique();
            $table->string('no_hp');
            $table->unsignedBigInteger('id_prov');
            $table->unsignedBigInteger('id_kab');
            $table->unsignedBigInteger('id_kec');
            $table->text('detail_alamat')->nullable();
            $table->string('tempat_lahir');
            $table->date('tanggal_lahir');
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
        Schema::dropIfExists('data_agens');
    }
}
