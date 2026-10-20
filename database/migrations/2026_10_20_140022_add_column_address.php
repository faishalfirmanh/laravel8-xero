<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnAddress extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('hotels', 'address')) {
            Schema::table('hotels', function (Blueprint $table) {
                $table->string('address')->nullable();
            });
        }

        if (!Schema::hasColumn('hotels', 'distance')) {
            Schema::table('hotels', function (Blueprint $table) {
                $table->integer('distance')->nullable();
            });
        }

        if (!Schema::hasColumn('hotels', 'star')) {
            Schema::table('hotels', function (Blueprint $table) {
                $table->tinyInteger('star')->nullable();
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
        Schema::table('hotels', function (Blueprint $table) {
            //
        });
    }
}
