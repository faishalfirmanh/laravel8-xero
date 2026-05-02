<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnAccountId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('coas', function (Blueprint $table) {
            $table->string('account_uuid')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('currency_code', 10)->nullable();//sar idr
            $table->text('desc')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('coas', function (Blueprint $table) {
            $table->dropColumn('account_uuid');
            $table->dropColumn('is_active');
            $table->dropColumn('currency_code');
            $table->dropColumn('desc');
        });
    }
}
