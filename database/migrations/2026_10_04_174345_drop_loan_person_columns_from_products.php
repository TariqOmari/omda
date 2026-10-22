<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'loan_person_name',
                'loan_person_phone',
                'loan_due_date',
                'loan_note',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('loan_person_name', 150)->nullable();
            $table->string('loan_person_phone', 50)->nullable();
            $table->string('loan_due_date', 20)->nullable();
            $table->text('loan_note')->nullable();
        });
    }
};