<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_run_id')->constrained()->cascadeOnDelete();
            $table->enum('entity', ['category', 'product', 'stock']);
            $table->string('external_id', 191);
            $table->json('payload');
            $table->char('hash', 32);
            $table->boolean('is_valid');
            $table->string('error', 500)->nullable();

            $table->index(['import_run_id', 'entity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
    }
};
