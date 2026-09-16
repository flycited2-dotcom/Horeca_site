<?php

namespace App\Models;

use App\Enums\ImportEntity;
use Database\Factories\ImportRowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A normalized supplier record staged before it is applied to the catalog (TZ §6.3).
 */
#[Fillable(['import_run_id', 'entity', 'external_id', 'payload', 'hash', 'is_valid', 'error'])]
#[WithoutTimestamps]
class ImportRow extends Model
{
    /** @use HasFactory<ImportRowFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entity' => ImportEntity::class,
            'payload' => 'array',
            'is_valid' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ImportRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(ImportRun::class, 'import_run_id');
    }
}
