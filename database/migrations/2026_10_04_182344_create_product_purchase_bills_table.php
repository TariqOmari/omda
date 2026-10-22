<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_purchase_bills', function (Blueprint $table) {
            $table->id();

            // شماره فاکتور
            $table->string('bill_number', 50)->unique();

            // مراجع
            $table->foreignId('product_id')
                  ->constrained('products')
                  ->cascadeOnDelete();

            $table->foreignId('supplier_id')
                  ->nullable()
                  ->constrained('suppliers')
                  ->nullOnDelete();

            // ----- snapshot محصول -----
            $table->string('product_name');
            $table->string('category')->nullable();
            $table->string('barcode', 100)->nullable();
            $table->string('photo')->nullable();

            // واحدها
            $table->string('module_name');
            $table->string('sub_module_name')->nullable();
            $table->integer('sub_module_qty')->default(1);
            $table->string('sub_sub_module_name')->nullable();
            $table->integer('sub_sub_module_qty')->default(1);

            // ----- snapshot قیمت -----
            $table->string('price_mode', 30);
            $table->decimal('unit_price', 18, 2);
            $table->string('currency', 5);                 // AFN | USD
            $table->decimal('exchange_rate', 15, 4);       // 1 USD = X AFN

            $table->integer('qty');
            $table->integer('final_qty');
            $table->decimal('final_price', 18, 2);

            $table->decimal('final_price_afn', 18, 2);
            $table->decimal('final_price_usd', 18, 2);

            // ----- snapshot پرداخت -----
            $table->string('payment_type', 30);            // cash | loan_full | loan_partial
            $table->decimal('paid_amount', 18, 2);
            $table->decimal('paid_amount_afn', 18, 2);
            $table->decimal('paid_amount_usd', 18, 2);

            $table->decimal('remaining_amount', 18, 2);
            $table->decimal('remaining_amount_afn', 18, 2);
            $table->decimal('remaining_amount_usd', 18, 2);

            // ----- snapshot تامین‌کننده -----
            $table->string('supplier_name')->nullable();
            $table->string('supplier_phone', 50)->nullable();
            $table->string('supplier_address')->nullable();

            // ----- تواریخ -----
            $table->string('production_date', 20)->nullable();
            $table->string('expiry_date', 20)->nullable();

            $table->timestamps();

            $table->index('supplier_id');
            $table->index('product_id');
            $table->index('payment_type');
            $table->index('currency');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_purchase_bills');
    }
};