<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDataJamaahXerosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('data_jamaah_xeros', function (Blueprint $table) {
            $table->id();
            $table->string('uuid_contact')->nullable();
            $table->string('full_name');
            $table->string('phone_number')->nullable();
            $table->boolean('is_jamaah')->default(false);
            $table->boolean('is_agen')->default(false);
            $table->boolean('is_mitra_trevel')->default(false);
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
        Schema::dropIfExists('data_jamaah_xeros');
    }
}
