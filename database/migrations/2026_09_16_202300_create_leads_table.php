<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['callback', 'question', 'price_request', 'availability_request', 'analog_request', 'one_click', 'not_found']);
            $table->string('name', 150)->nullable();
            $table->string('phone', 20);
            $table->string('email', 150)->nullable();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->text('message')->nullable();
            $table->enum('status', ['new', 'in_work', 'done'])->default('new');
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('utm')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
