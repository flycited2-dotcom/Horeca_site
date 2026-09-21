<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Сравнение вошедшего клиента (ТЗ §8.5): переживает выход и вход. Гостевой список живёт
 * в сессии и переносится сюда при входе.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compare_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compare_items');
    }
};
