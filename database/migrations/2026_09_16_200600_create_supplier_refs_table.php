<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_refs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->enum('entity', ['category', 'brand', 'warehouse', 'attribute']);
            $table->string('external_key', 191);
            $table->string('name', 255);
            $table->string('parent_key', 191)->nullable();
            $table->unsignedBigInteger('local_id')->nullable();
            $table->boolean('is_ignored')->default(false);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'entity', 'external_key']);
            $table->index(['entity', 'local_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_refs');
    }
};
