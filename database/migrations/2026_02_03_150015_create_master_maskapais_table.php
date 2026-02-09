<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasterMaskapaisTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
public function up()
{
    Schema::create('master_maskapais', function (Blueprint $table) {
        $table->id();
        $table->string('nama_maskapai');
        $table->string('created_by')->nullable();
        $table->boolean('is_active')->default(1);
        $table->timestamps();
    });
}

public function down()
{
    Schema::dropIfExists('master_maskapais');
}
}
