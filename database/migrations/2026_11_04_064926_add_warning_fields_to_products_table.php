<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('low_stock_warning')->default(0)->after('sub_sub_module_final_qty');
            $table->integer('high_stock_warning')->default(0)->after('low_stock_warning');
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['low_stock_warning', 'high_stock_warning']);
        });
    }
};