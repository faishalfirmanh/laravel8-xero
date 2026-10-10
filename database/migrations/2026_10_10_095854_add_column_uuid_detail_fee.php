<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnUuidDetailFee extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('fee_overpays', function (Blueprint $table) {
            $table->string('uuid_details_feeoverpay')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('fee_overpays', function (Blueprint $table) {
            Schema::dropIfExists('uuid_details_feeoverpay');
        });
    }
}
