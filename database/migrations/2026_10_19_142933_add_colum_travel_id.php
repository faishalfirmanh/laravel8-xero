<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumTravelId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('branding_themes', function (Blueprint $table) {
            $table->unsignedBigInteger('travel_id')->nullable()->after('name');
            $table->text('bank_accounts')->nullable()->after('signature_title');
            $table->index('travel_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('branding_themes', function (Blueprint $table) {
            $table->dropIndex(['travel_id']);
            $table->dropColumn(['travel_id', 'bank_accounts']);
        });
    }
}
