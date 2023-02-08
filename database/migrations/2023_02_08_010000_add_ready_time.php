<?php

namespace CupNoodles\RelayDelivery\Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Schema;

/**
 * 
 */
class AddReadyTime extends Migration
{
    
     /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->datetime('relay_ready_time');
        });

    }
    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'relay_ready_time')) {
                $table->dropColumn('relay_ready_time');
            }
        });
    }

}
