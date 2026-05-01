<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnPaid extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('p_bills', function (Blueprint $table) {
            $table->decimal('nominal_paid', 19, 4)->default(0);
            $table->decimal('nominal_due', 19, 4)->default(0);
            $table->tinyInteger('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('p_bills', function (Blueprint $table) {
            $table->dropColumn('nominal_paid');
            $table->dropColumn('nominal_due');
            $table->dropColumn('status');
        });
    }
}
