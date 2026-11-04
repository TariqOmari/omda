<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_purchase_bills', function (Blueprint $table) {
            $table->json('product_ids')->nullable()->after('bill_number');
        });

        Schema::table('product_purchase_bills', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropIndex(['product_id']);
            $table->dropColumn('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_purchase_bills', function (Blueprint $table) {
            $table->foreignId('product_id')
                  ->nullable()
                  ->after('bill_number')
                  ->constrained('products')
                  ->cascadeOnDelete();
        });

        Schema::table('product_purchase_bills', function (Blueprint $table) {
            $table->dropColumn('product_ids');
        });
    }
};