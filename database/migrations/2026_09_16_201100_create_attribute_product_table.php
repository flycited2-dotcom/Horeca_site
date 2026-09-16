<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_product', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('value_string', 255)->nullable();
            $table->decimal('value_number', 14, 3)->nullable();
            $table->boolean('value_bool')->nullable();
            $table->string('raw_value', 255)->nullable();
            $table->enum('source', ['supplier', 'manual'])->default('supplier');

            $table->primary(['product_id', 'attribute_id']);
            $table->index(['attribute_id', 'value_number']);
            $table->index(['attribute_id', 'value_string']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_product');
    }
};
