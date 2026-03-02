<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateXeroAccountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
    Schema::create('xero_accounts', function (Blueprint $table) {
        $table->id();
        $table->string('xero_account_id')->unique();
        $table->string('code');
        $table->string('name');
        $table->text('description')->nullable(); // 👈 tambahin ini
        $table->string('type'); // Revenue, Expense, Asset, etc
        $table->string('tax_type')->nullable();
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
        Schema::dropIfExists('xero_accounts');
    }
}
