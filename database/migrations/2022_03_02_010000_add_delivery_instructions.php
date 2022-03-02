<?php

namespace CupNoodles\RelayDelivery\Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Schema;

/**
 * 
 */
class AddDeliveryInstructions extends Migration
{
    
     /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_instructions', 255);
        });

    }
    /**
     * Reverse the migrations.
     */
    public function down()
    {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('delivery_instructions');
            });
    }

}
