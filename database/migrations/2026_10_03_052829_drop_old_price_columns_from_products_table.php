<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('products', 'price_per_module')) {
                $columns[] = 'price_per_module';
            }
            if (Schema::hasColumn('products', 'price_per_sub_module')) {
                $columns[] = 'price_per_sub_module';
            }

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }

    public function down(): void
    {
        // no rollback needed
    }
};