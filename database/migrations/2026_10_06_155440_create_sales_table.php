<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 50)->unique();

            // مشتری
            $table->foreignId('customer_id')
                  ->nullable()
                  ->constrained('customers')
                  ->nullOnDelete();
            $table->string('customer_name')->nullable();

            // محصول
            $table->foreignId('product_id')
                  ->constrained('products')
                  ->cascadeOnDelete();
            $table->string('product_name');

            // واحد فروش
            $table->string('sale_unit', 20);              // module | sub_module | sub_sub_module
            $table->string('sale_unit_name');             // نام واحد (کارتن/بسته/قوطی)

            // تعداد فروخته‌شده در همان واحد
            $table->integer('qty_sold');

            // تبدیل به کل واحدهای مصرف‌شده (snapshot)
            $table->integer('cartons_used')->default(0);
            $table->integer('sub_units_used')->default(0);   // کل بسته
            $table->integer('pieces_used')->default(0);      // کل قوطی

            // snapshot بسته‌بندی محصول
            $table->string('module_name')->nullable();
            $table->string('sub_module_name')->nullable();
            $table->integer('sub_module_qty')->default(1);
            $table->string('sub_sub_module_name')->nullable();
            $table->integer('sub_sub_module_qty')->default(1);

            // قیمت فروش (دستی وارد شده)
            $table->decimal('unit_price', 15, 2);         // قیمت هر واحد فروش
            $table->string('currency', 5);                // AFN | USD
            $table->decimal('exchange_rate', 15, 4);

            $table->decimal('total_price', 15, 2);        // unit_price × qty_sold
            $table->decimal('total_price_afn', 18, 2);
            $table->decimal('total_price_usd', 18, 2);

            // پرداخت مشتری
            $table->string('payment_type', 20);           // cash | loan_full | loan_partial
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->decimal('paid_amount_afn', 18, 2)->default(0);
            $table->decimal('paid_amount_usd', 18, 2)->default(0);

            $table->decimal('remaining_amount', 18, 2)->default(0);
            $table->decimal('remaining_amount_afn', 18, 2)->default(0);
            $table->decimal('remaining_amount_usd', 18, 2)->default(0);

            // سود / ضرر
            $table->decimal('cost_price', 15, 2)->default(0);   // هزینه‌ی خرید این مقدار (محاسبه‌شده)
            $table->decimal('profit', 15, 2)->default(0);       // total_price - cost_price

            $table->text('note')->nullable();

            $table->timestamps();

            $table->index('customer_id');
            $table->index('product_id');
            $table->index('payment_type');
            $table->index('currency');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};