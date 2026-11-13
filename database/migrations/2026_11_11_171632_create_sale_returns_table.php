<?php
// database/migrations/xxxx_xx_xx_create_sale_returns_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number', 50)->unique();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();

            // snapshot of the original sale
            $table->string('invoice_number', 50)->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_name')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('customer_name')->nullable();

            // what is being returned (unit + qty)
            $table->string('return_unit', 30); // module | sub_module | sub_sub_module
            $table->string('return_unit_name')->nullable();
            $table->integer('qty_returned');

            // how many pieces were actually returned to stock
            $table->integer('pieces_returned')->default(0);
            $table->integer('cartons_returned')->default(0);
            $table->integer('sub_units_returned')->default(0);

            // money
            $table->decimal('unit_price', 15, 2)->default(0);        // selling price per unit
            $table->decimal('total_price', 15, 2)->default(0);       // qty * unit_price
            $table->decimal('cost_price', 15, 2)->default(0);        // COST of returned pieces (capital back)
            $table->decimal('profit_reversed', 15, 2)->default(0);   // profit lost

            $table->string('currency', 5)->default('AFN');
            $table->decimal('exchange_rate', 15, 4)->default(1);

            $table->decimal('total_price_afn', 15, 2)->default(0);
            $table->decimal('total_price_usd', 15, 2)->default(0);
            $table->decimal('cost_price_afn', 15, 2)->default(0);
            $table->decimal('cost_price_usd', 15, 2)->default(0);

            // how the money is given back
            $table->enum('refund_type', ['cash', 'reduce_debt', 'exchange'])->default('cash');
            $table->decimal('refund_amount', 15, 2)->default(0);
            $table->decimal('refund_amount_afn', 15, 2)->default(0);
            $table->decimal('refund_amount_usd', 15, 2)->default(0);

            // customer debt effect: if sale was loan, returning reduces debt
            $table->decimal('debt_reduced', 15, 2)->default(0);

            $table->string('return_date', 20)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_returns');
    }
};