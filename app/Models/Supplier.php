<?php

namespace App\Models;

use App\Casts\PercentCast;
use App\Enums\PriceKind;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'price_kind', 'markup_percent', 'retail_round_to', 'config', 'is_active'])]
#[Hidden(['config'])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_kind' => PriceKind::class,
            'markup_percent' => PercentCast::class,
            'retail_round_to' => 'integer',
            'config' => 'encrypted:array',
            'is_active' => 'boolean',
            'last_import_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<Warehouse, $this>
     */
    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    /**
     * @return HasMany<ImportProfile, $this>
     */
    public function importProfiles(): HasMany
    {
        return $this->hasMany(ImportProfile::class);
    }

    /**
     * @return HasMany<SupplierRef, $this>
     */
    public function refs(): HasMany
    {
        return $this->hasMany(SupplierRef::class);
    }
}
