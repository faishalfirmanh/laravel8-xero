<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('master_pengeluaran_pakets', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->after('is_active');
        });
    }

    public function down()
    {
        Schema::table('master_pengeluaran_pakets', function (Blueprint $table) {
           $table->foreignId('created_by')
      ->nullable()
      ->constrained('users')
      ->nullOnDelete();

        });
    }
};
