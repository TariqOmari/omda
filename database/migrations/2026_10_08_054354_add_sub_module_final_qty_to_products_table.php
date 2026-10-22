<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('sub_module_final_qty')
                  ->default(0)
                  ->after('final_qty');

            $table->unsignedInteger('sub_sub_module_final_qty')
                  ->default(0)
                  ->after('sub_module_final_qty');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['sub_module_final_qty', 'sub_sub_module_final_qty']);
        });
    }
};