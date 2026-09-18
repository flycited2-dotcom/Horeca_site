<?php

namespace App\Models;

use App\Enums\ImportRunStatus;
use App\Enums\ImportTrigger;
use Database\Factories\ImportRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'import_profile_id', 'user_id', 'trigger', 'status', 'is_dry_run', 'file_path', 'file_hash', 'source_version',
    'rows_total', 'created', 'updated', 'unchanged', 'discontinued', 'errors',
    'log', 'log_file', 'error_message', 'started_at', 'finished_at',
])]
class ImportRun extends Model
{
    /** @use HasFactory<ImportRunFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trigger' => ImportTrigger::class,
            'status' => ImportRunStatus::class,
            'is_dry_run' => 'boolean',
            'rows_total' => 'integer',
            'created' => 'integer',
            'updated' => 'integer',
            'unchanged' => 'integer',
            'discontinued' => 'integer',
            'errors' => 'integer',
            'log' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ImportProfile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(ImportProfile::class, 'import_profile_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ImportRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }
}
