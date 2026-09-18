<?php

namespace App\Models;

use Database\Factories\ImportProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * last_etag and last_modified are written only by the import runner.
 */
#[Fillable(['supplier_id', 'name', 'source', 'url', 'schedule', 'settings', 'is_active'])]
class ImportProfile extends Model
{
    /** @use HasFactory<ImportProfileFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return HasMany<ImportRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(ImportRun::class);
    }

    /**
     * The most recent run, whatever its outcome.
     *
     * @return HasOne<ImportRun, $this>
     */
    public function latestRun(): HasOne
    {
        return $this->hasOne(ImportRun::class)->latestOfMany('id');
    }
}
