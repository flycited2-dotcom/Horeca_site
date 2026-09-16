<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('source', 64);
            $table->string('url', 500)->nullable();
            $table->string('schedule', 64)->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(false);
            $table->string('last_etag', 191)->nullable();
            $table->string('last_modified', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_profiles');
    }
};
