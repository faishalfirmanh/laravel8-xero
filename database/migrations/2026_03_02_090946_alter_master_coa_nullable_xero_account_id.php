<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterMasterCoaNullableXeroAccountId extends Migration
{
    public function up()
    {
        Schema::table('master_coa', function (Blueprint $table) {
            $table->string('xero_account_id')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('master_coa', function (Blueprint $table) {
            $table->string('xero_account_id')->nullable(false)->change();
        });
    }
}