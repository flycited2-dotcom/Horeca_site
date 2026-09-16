<?php

namespace App\Models;

use App\Enums\SupplierRefEntity;
use Database\Factories\SupplierRefFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Links a supplier entity (category, brand, warehouse, attribute) to our record.
 */
#[Fillable(['supplier_id', 'entity', 'external_key', 'name', 'parent_key', 'local_id', 'is_ignored', 'last_seen_at'])]
class SupplierRef extends Model
{
    /** @use HasFactory<SupplierRefFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entity' => SupplierRefEntity::class,
            'local_id' => 'integer',
            'is_ignored' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
