<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'sub_sub_module_name')) {
                $table->string('sub_sub_module_name')->nullable()->after('sub_module_qty');
            }
            if (!Schema::hasColumn('products', 'sub_sub_module_qty')) {
                $table->unsignedInteger('sub_sub_module_qty')->nullable()->after('sub_sub_module_name');
            }
        });

        // Change enum to include third option
        // Note: MySQL only. For SQLite/Postgres, do manual ALTER.
        if (config('database.default') === 'mysql') {
            \DB::statement("ALTER TABLE products MODIFY COLUMN price_mode ENUM('module','sub_module','sub_sub_module') DEFAULT 'module'");
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['sub_sub_module_name', 'sub_sub_module_qty']);
        });
    }
};