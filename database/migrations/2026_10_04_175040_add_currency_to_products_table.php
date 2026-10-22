<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->enum('currency', ['AFN', 'USD'])->default('AFN')->after('price');

            // نرخ تبادل روز (1 USD = X AFN)
            $table->decimal('exchange_rate', 15, 4)->default(1)->after('currency');

            // معادل‌ها
            $table->decimal('final_price_afn', 18, 2)->default(0)->after('remaining_amount');
            $table->decimal('final_price_usd', 18, 2)->default(0)->after('final_price_afn');

            $table->decimal('paid_amount_afn', 18, 2)->default(0)->after('final_price_usd');
            $table->decimal('paid_amount_usd', 18, 2)->default(0)->after('paid_amount_afn');

            $table->decimal('remaining_amount_afn', 18, 2)->default(0)->after('paid_amount_usd');
            $table->decimal('remaining_amount_usd', 18, 2)->default(0)->after('remaining_amount_afn');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'currency', 'exchange_rate',
                'final_price_afn', 'final_price_usd',
                'paid_amount_afn', 'paid_amount_usd',
                'remaining_amount_afn', 'remaining_amount_usd',
            ]);
        });
    }
};