<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained();
            $table->string('external_id', 64);
            $table->string('supplier_code', 64)->nullable()->index();
            $table->string('sku', 64)->nullable()->index();
            $table->string('model', 255)->nullable();
            $table->string('name', 255);
            $table->string('slug', 160)->unique();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->string('unit', 16)->default('шт');
            $table->decimal('rrp_price', 12, 2)->nullable();
            $table->decimal('purchase_price', 12, 2)->nullable();
            $table->decimal('retail_price', 12, 2)->nullable()->index();
            $table->decimal('old_price', 12, 2)->nullable();
            $table->enum('availability', ['in_stock', 'low', 'incoming', 'on_order', 'discontinued'])->default('on_order');
            $table->tinyInteger('availability_rank')->default(3);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_new')->default(false);
            $table->boolean('is_hit')->default(false);
            $table->decimal('weight_kg', 8, 3)->nullable();
            $table->unsignedInteger('length_mm')->nullable();
            $table->unsignedInteger('width_mm')->nullable();
            $table->unsignedInteger('height_mm')->nullable();
            $table->smallInteger('warranty_months')->nullable();
            $table->string('meta_title', 255)->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('h1', 255)->nullable();
            $table->longText('seo_text')->nullable();
            $table->text('search_text')->nullable();
            $table->integer('popularity')->default(0);
            $table->json('locked_fields')->nullable();
            $table->char('source_hash', 32)->nullable();
            $table->smallInteger('missing_runs')->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['supplier_id', 'external_id']);
            $table->index(['is_visible', 'availability_rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
