<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('slug', 160);
            $table->string('city', 150)->nullable();
            $table->smallInteger('delivery_days_min')->nullable();
            $table->smallInteger('delivery_days_max')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->smallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['supplier_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
