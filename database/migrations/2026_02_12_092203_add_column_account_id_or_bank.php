<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnAccountIdOrBank extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('payments_history_fixes', function (Blueprint $table) {
            $table->string('account_uuid_or_bank')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payments_history_fixes', function (Blueprint $table) {
              Schema::dropIfExists('account_uuid_or_bank');
        });
    }
}
