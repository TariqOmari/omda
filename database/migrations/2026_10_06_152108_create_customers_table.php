<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            $table->string('name');                    // 👈 تنها فیلد اجباری
            $table->string('lastname')->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('phone', 50)->nullable();
            $table->string('whatsapp', 50)->nullable();
            $table->string('address')->nullable();
            $table->string('profile_photo')->nullable();

            $table->timestamps();

            $table->index('name');
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};