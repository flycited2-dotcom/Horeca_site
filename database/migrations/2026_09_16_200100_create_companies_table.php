<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('legal_name', 255);
            $table->string('brand_name', 255)->nullable();
            $table->string('inn', 12)->index();
            $table->string('kpp', 9)->nullable();
            $table->string('ogrn', 15)->nullable();
            $table->string('legal_address', 500)->nullable();
            $table->string('delivery_address', 500)->nullable();
            $table->string('city', 150)->nullable();
            $table->string('bank_name', 255)->nullable();
            $table->string('bik', 9)->nullable();
            $table->string('account', 20)->nullable();
            $table->string('corr_account', 20)->nullable();
            $table->string('contact_person', 150);
            $table->string('phone', 20);
            $table->string('email', 150);
            $table->enum('segment', ['restaurant', 'cafe', 'bar', 'hotel', 'canteen', 'bakery', 'production', 'retail_chain', 'other']);
            $table->enum('status', ['pending', 'approved', 'rejected', 'blocked'])->default('pending');
            $table->foreignId('price_tier_id')->nullable()->constrained('price_tiers')->nullOnDelete();
            $table->text('manager_comment')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
