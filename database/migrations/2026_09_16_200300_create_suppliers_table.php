<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 100)->unique();
            $table->enum('price_kind', ['rrp', 'dealer'])->default('rrp');
            $table->decimal('markup_percent', 5, 2)->default(0);
            $table->smallInteger('retail_round_to')->default(1);
            $table->text('config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_import_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
