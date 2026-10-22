<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->enum('payment_type', ['cash', 'loan_full', 'loan_partial'])
                  ->default('cash')
                  ->after('final_price');

            $table->decimal('paid_amount', 15, 2)->default(0)->after('payment_type');
            $table->decimal('remaining_amount', 15, 2)->default(0)->after('paid_amount');

            // اطلاعات قرض
            $table->string('loan_person_name', 150)->nullable()->after('remaining_amount');
            $table->string('loan_person_phone', 50)->nullable()->after('loan_person_name');
            $table->string('loan_due_date', 20)->nullable()->after('loan_person_phone');
            $table->text('loan_note')->nullable()->after('loan_due_date');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'payment_type', 'paid_amount', 'remaining_amount',
                'loan_person_name', 'loan_person_phone',
                'loan_due_date', 'loan_note',
            ]);
        });
    }
};