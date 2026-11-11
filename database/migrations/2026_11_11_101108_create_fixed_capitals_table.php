<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_capitals', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->decimal('amount', 15, 2)->default(0);
            $table->enum('currency', ['AFN', 'USD'])->default('AFN');
            $table->decimal('exchange_rate', 12, 4)->default(1);
            $table->string('entry_date', 20)->nullable(); // 👈 Jalali string (like stock_entry_date in products)
            $table->string('note', 1000)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_capitals');
    }
};