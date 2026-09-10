<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnVaNumberOnItem extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('items_paket_all_from_xeros', function (Blueprint $table) {
            //
            if (!Schema::hasColumn('items_paket_all_from_xeros', 'va_number')) {
                $table->string('va_number')->nullable();
            }

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('items_paket_all_from_xeros', function (Blueprint $table) {
            $table->dropColumn('va_number');
        });
    }
}
