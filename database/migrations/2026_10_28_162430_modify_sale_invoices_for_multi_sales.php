<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_invoices', function (Blueprint $table) {
            // Add the new array column
            $table->json('sale_ids')->nullable()->after('id');
        });

        // Drop the old single sale_id constraint and column
        Schema::table('sale_invoices', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
            $table->dropColumn('sale_id');
        });
    }

    public function down(): void
    {
        Schema::table('sale_invoices', function (Blueprint $table) {
            $table->foreignId('sale_id')->nullable()->constrained('sales')->cascadeOnDelete();
        });

        Schema::table('sale_invoices', function (Blueprint $table) {
            $table->dropColumn('sale_ids');
        });
    }
};