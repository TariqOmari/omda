<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'price_mode')) {
                $table->enum('price_mode', ['module', 'sub_module'])
                      ->default('module')
                      ->after('sub_module_qty');
            }

            if (!Schema::hasColumn('products', 'price')) {
                $table->decimal('price', 12, 2)
                      ->default(0)
                      ->after('price_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['price_mode', 'price']);
        });
    }
};