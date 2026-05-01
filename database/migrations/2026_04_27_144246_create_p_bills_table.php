<?php

use Doctrine\DBAL\Types\SmallIntType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePBillsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('p_bills', function (Blueprint $table) {
            $table->id();
            $table->string('uuid_from');
            $table->date('date_req');
            $table->date('due_date');
            $table->string('reference');
            $table->tinyInteger('amounts_are')->default(0);//tax exclude = 2, tax inclusive = 1, no tax = 0

            $table->decimal('subtotal', 19, 4)->default(0);
            $table->decimal('total', 19, 4)->default(0);
            $table->decimal('tax', 19, 4)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('p_bills');
    }
}
