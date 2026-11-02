<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnContactIdHotel extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        if (!Schema::hasColumn('invoices_hotels', 'contact_id_x')) {
            Schema::table('invoices_hotels', function (Blueprint $table) {
                $table->unsignedBigInteger('contact_id_x')->nullable();
                $table->foreign('contact_id_x')
                    ->references('id')
                    ->on('data_jamaah_xeros')
                    ->onDelete('cascade');
            });
        }

        if (!Schema::hasColumn('invoices_hotels', 'contact_id_alhid')) {
            Schema::table('invoices_hotels', function (Blueprint $table) {
                $table->unsignedBigInteger('contact_id_alhid')->nullable();
                $table->foreign('contact_id_alhid')
                    ->references('id')
                    ->on('data_jamaah_xeros')
                    ->onDelete('cascade');
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
        Schema::table('invoices_hotels', function (Blueprint $table) {
            $table->dropColumn('contact_id_x');
            $table->dropColumn('contact_id_alhid');
        });
    }
}
