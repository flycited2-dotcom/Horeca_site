<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A storefront category. The supplier tree is mirrored into it by the import,
 * after that names, structure and activity belong to the manager (TZ §6.6).
 */
#[Fillable([
    'parent_id', 'name', 'slug', 'description', 'icon', 'show_on_home',
    'meta_title', 'meta_description', 'h1', 'seo_text', 'sort', 'is_active',
])]
class Category extends Model implements HasMedia
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, InteractsWithMedia;

    /**
     * Картинка плитки раздела, загруженная менеджером; без неё плитка берёт фото товара.
     */
    public const string IMAGE = 'image';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'show_on_home' => 'boolean',
            'is_active' => 'boolean',
            'sort' => 'integer',
            'products_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @param  Builder<Category>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<Category>  $query
     */
    #[Scope]
    protected function roots(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::IMAGE)
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    /**
     * Плитка раздела должна показаться сразу после загрузки, поэтому уменьшенная копия
     * делается не в очереди; внешние оптимизаторы не нужны — WebP уже сжат (как у фото товара).
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('tile')
            ->fit(Fit::Max, 600, 600)
            ->format('webp')
            ->nonOptimized()
            ->nonQueued()
            ->performOnCollections(self::IMAGE);
    }
}
