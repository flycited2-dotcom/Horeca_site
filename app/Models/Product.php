<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\Availability;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable([
    'supplier_id', 'external_id', 'supplier_code', 'sku', 'model', 'name', 'slug', 'category_id', 'brand_id',
    'short_description', 'description', 'unit', 'rrp_price', 'purchase_price', 'retail_price', 'old_price',
    'availability', 'is_visible', 'is_new', 'is_hit', 'weight_kg', 'length_mm', 'width_mm', 'height_mm',
    'warranty_months', 'meta_title', 'meta_description', 'h1', 'seo_text',
])]
class Product extends Model implements HasMedia
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, InteractsWithMedia, SoftDeletes;

    public const string IMAGES = 'images';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'unit' => 'шт',
        'availability' => 'on_order',
        'availability_rank' => 3,
        'is_visible' => true,
        'is_new' => false,
        'is_hit' => false,
    ];

    protected static function booted(): void
    {
        // The sort weight is always derived from availability, never set on its own.
        static::saving(function (Product $product): void {
            $product->availability_rank = $product->availability->rank();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rrp_price' => MoneyCast::class,
            'purchase_price' => MoneyCast::class,
            'retail_price' => MoneyCast::class,
            'old_price' => MoneyCast::class,
            'availability' => Availability::class,
            'availability_rank' => 'integer',
            'is_visible' => 'boolean',
            'is_new' => 'boolean',
            'is_hit' => 'boolean',
            'weight_kg' => 'decimal:3',
            'length_mm' => 'integer',
            'width_mm' => 'integer',
            'height_mm' => 'integer',
            'warranty_months' => 'integer',
            'popularity' => 'integer',
            'locked_fields' => 'array',
            'missing_runs' => 'integer',
            'last_synced_at' => 'datetime',
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
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return HasMany<ProductStock, $this>
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    /**
     * @return BelongsToMany<Attribute, $this>
     */
    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class)
            ->withPivot(['value_string', 'value_number', 'value_bool', 'raw_value', 'source']);
    }

    /**
     * @return HasMany<ProductPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function relatedProducts(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'related_products', 'product_id', 'related_id')
            ->withPivot('sort')
            ->orderByPivot('sort');
    }

    /**
     * @return BelongsToMany<ProductCollection, $this>
     */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(ProductCollection::class, 'collection_product', 'product_id', 'collection_id')
            ->withPivot('sort');
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<Favorite, $this>
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function isPriceOnRequest(): bool
    {
        return $this->retail_price === null;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::IMAGES)
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    /**
     * WebP in three sizes without upscaling (TZ §5.2). External image optimizers are
     * skipped: they are not installed on the servers and WebP is already compressed.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        foreach (['thumb' => 160, 'card' => 600, 'full' => 1200] as $name => $size) {
            $this->addMediaConversion($name)
                ->fit(Fit::Max, $size, $size)
                ->format('webp')
                ->nonOptimized()
                ->performOnCollections(self::IMAGES);
        }
    }
}
