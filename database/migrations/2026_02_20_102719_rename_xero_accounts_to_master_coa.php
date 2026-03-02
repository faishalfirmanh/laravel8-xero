<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameXeroAccountsToMasterCoa extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
public function up()
{
    Schema::rename('xero_accounts', 'master_coa');
}

public function down()
{
    Schema::rename('master_coa', 'xero_accounts');
}
}
