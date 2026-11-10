<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->integer('qty_cartons');
            $table->integer('qty_cans')->default(0);
            $table->decimal('price', 15, 2);
            $table->string('currency', 10)->default('AFN');
            $table->date('import_date');
            $table->timestamps();
        });
    }
    public function down() {
        Schema::dropIfExists('product_batches');
    }
};