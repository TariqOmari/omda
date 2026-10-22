<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_invoices', function (Blueprint $table) {
            $table->id();

            // 🔗 اتصال به فروش
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->string('invoice_number')->unique();

            // 👤 معلومات مشتری (snapshot)
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('customer_lastname')->nullable();
            $table->string('customer_phone')->nullable();
            $table->text('customer_address')->nullable();

            // 📦 معلومات محصول (snapshot)
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_name')->nullable();
            $table->string('product_category')->nullable();
            $table->string('product_barcode')->nullable();
            $table->string('product_photo')->nullable();

            // 🧱 واحدها
            $table->string('sale_unit')->nullable();
            $table->string('sale_unit_name')->nullable();
            $table->integer('qty_sold')->default(0);

            $table->integer('cartons_used')->default(0);
            $table->integer('sub_units_used')->default(0);
            $table->integer('pieces_used')->default(0);

            $table->string('module_name')->nullable();
            $table->string('sub_module_name')->nullable();
            $table->integer('sub_module_qty')->default(1);
            $table->string('sub_sub_module_name')->nullable();
            $table->integer('sub_sub_module_qty')->default(1);

            // 💰 قیمت‌ها
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->string('currency', 10)->default('AFN');
            $table->decimal('exchange_rate', 15, 4)->default(1);

            $table->decimal('total_price', 15, 2)->default(0);
            $table->decimal('total_price_afn', 15, 2)->default(0);
            $table->decimal('total_price_usd', 15, 2)->default(0);

            // 💵 پرداخت
            $table->string('payment_type')->default('cash');

            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('paid_amount_afn', 15, 2)->default(0);
            $table->decimal('paid_amount_usd', 15, 2)->default(0);

            $table->decimal('remaining_amount', 15, 2)->default(0);
            $table->decimal('remaining_amount_afn', 15, 2)->default(0);
            $table->decimal('remaining_amount_usd', 15, 2)->default(0);

            // 📊 سود
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->decimal('profit', 15, 2)->default(0);

            // 📅 معلومات اضافی
            $table->string('sale_date')->nullable();
            $table->text('note')->nullable();

            // 🏢 معلومات فروشنده/تأمین‌کننده
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('supplier_name')->nullable();

            // وضعیت فاکتور
            $table->string('status')->default('active'); // active, cancelled

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_invoices');
    }
};