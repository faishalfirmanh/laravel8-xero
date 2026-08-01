<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVaTransUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('va_trans_users', function (Blueprint $table) {
            $table->id();
            $table->string('inv_number');
            $table->string('va_number');
            $table->string('paket_name');
            $table->string('bank_name');
            $table->string('name_contact');
            $table->decimal('payment', 19, 4)->default(0);
            $table->decimal('total_nominal', 19, 4)->default(0);
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
        Schema::dropIfExists('va_trans_users');
    }
}
