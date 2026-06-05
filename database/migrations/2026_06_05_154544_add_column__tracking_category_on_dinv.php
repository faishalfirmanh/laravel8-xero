<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnTrackingCategoryOnDinv extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('item_detail_invoices', function (Blueprint $table) {
            $table->string('paket_tracking_uuid')->nullable();
            $table->string('divisi_travel_tracking_uuid')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('item_detail_invoices', function (Blueprint $table) {
            $table->dropColumn('paket_tracking_uuid');
            $table->dropColumn('divisi_travel_tracking_uuid');
        });
    }
}
