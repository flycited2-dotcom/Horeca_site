<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 16)->unique();
            $table->char('idempotency_key', 36)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['retail', 'wholesale']);
            $table->enum('status', ['new', 'processing', 'confirmed', 'invoiced', 'paid', 'shipped', 'completed', 'canceled'])->default('new');
            $table->string('customer_name', 150);
            $table->string('phone', 20);
            $table->string('email', 150)->nullable();
            $table->boolean('is_legal_entity')->default(false);
            $table->string('inn', 12)->nullable();
            $table->string('company_name', 255)->nullable();
            $table->enum('delivery_method', ['pickup', 'transport_company', 'courier_city']);
            $table->string('delivery_city', 150)->nullable();
            $table->string('delivery_address', 500)->nullable();
            $table->string('tk_name', 100)->nullable();
            $table->enum('payment_method', ['invoice', 'cash', 'card_on_delivery', 'online'])->default('invoice');
            $table->text('comment')->nullable();
            $table->text('manager_comment')->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount', 12, 2);
            $table->decimal('total', 12, 2);
            $table->string('invoice_path', 500)->nullable();
            $table->string('payment_id', 64)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('utm')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
