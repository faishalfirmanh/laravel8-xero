<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnLessNominalOnPinv extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('invoices_all_from_xeros', function (Blueprint $table) {
            $table->decimal('less_nominal', 19, 4)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('invoices_all_from_xeros', function (Blueprint $table) {
            $table->dropColumn('less_nominal');
        });
    }
}
