<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnNameOnBills extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('p_bills', 'created_by_name')) {
            Schema::table('p_bills', function (Blueprint $table) {
                $table->string('created_by_name')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('p_bills', function (Blueprint $table) {
            $table->dropColumn('created_by_name');
        });
    }
}
