<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('trigger', ['schedule', 'manual', 'cli']);
            $table->enum('status', ['queued', 'running', 'success', 'skipped', 'failed']);
            $table->boolean('is_dry_run')->default(false);
            $table->string('file_path', 500)->nullable();
            $table->string('source_version', 191)->nullable();
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('created')->default(0);
            $table->unsignedInteger('updated')->default(0);
            $table->unsignedInteger('unchanged')->default(0);
            $table->unsignedInteger('discontinued')->default(0);
            $table->unsignedInteger('errors')->default(0);
            $table->json('log')->nullable();
            $table->string('log_file', 500)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_runs');
    }
};
