<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The supplier re-uploads feeds several times a day and the ETag changes even when
 * the content does not, so an unchanged file is detected by its content hash.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_runs', function (Blueprint $table) {
            $table->char('file_hash', 32)->nullable()->after('file_path');
        });
    }

    public function down(): void
    {
        Schema::table('import_runs', function (Blueprint $table) {
            $table->dropColumn('file_hash');
        });
    }
};
