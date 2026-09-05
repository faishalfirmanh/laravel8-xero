<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnuuidTrackingCategoryOnItem extends Migration
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
            $table->string('uuid_tracking_category')->nullable();
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
            //
        });
    }
}
