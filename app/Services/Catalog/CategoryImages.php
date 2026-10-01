<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Картинки плиток разделов (ТЗ §9, макет — экраны 2 и 4).
 *
 * Картинку раздела выбирает менеджер; пока он не загрузил свою, плитка показывает фото
 * самого ходового товара раздела и его подразделов — того, что сейчас на витрине, в
 * наличии и популярнее остальных. Берутся только фото с готовой уменьшенной копией.
 * Результат живёт в кэше каталога: импорт и правки менеджера его сбрасывают.
 */
final class CategoryImages
{
    /**
     * Сколько подразделов страницы категории показываются плитками; остальные — чипами.
     */
    public const int SUBSECTION_TILES = 12;

    /**
     * В кэше «картинки нет» хранится пустой строкой: null кэш считает промахом.
     */
    private const string NONE = '';

    public function __construct(
        private readonly CategoryTree $tree,
        private readonly CatalogCache $cache,
        private readonly CatalogQuery $catalog,
    ) {}

    /**
     * Адреса картинок разделов одним обращением к кэшу и тремя запросами к базе на всё,
     * чего в кэше не нашлось.
     *
     * @param  list<int>  $ids
     * @return array<int, string|null> id раздела => адрес картинки или null
     */
    public function for(array $ids): array
    {
        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            return [];
        }

        $keys = [];

        foreach ($ids as $id) {
            $keys[$id] = $this->cache->key("category-image:{$id}");
        }

        $cached = Cache::many(array_values($keys));
        $images = [];
        $missing = [];

        foreach ($ids as $id) {
            $value = $cached[$keys[$id]] ?? null;

            if ($value === null) {
                $missing[] = $id;

                continue;
            }

            $images[$id] = $value === self::NONE ? null : $value;
        }

        if ($missing !== []) {
            $found = $this->resolve($missing);
            $store = [];

            foreach ($missing as $id) {
                $images[$id] = $found[$id] ?? null;
                $store[$keys[$id]] = $images[$id] ?? self::NONE;
            }

            Cache::putMany($store, now()->addDay());
        }

        return array_replace(array_fill_keys($ids, null), $images);
    }

    /**
     * Подразделы страницы категории для плиток и чипов: обычные данные для Livewire. Плитками
     * идут самые большие разделы в порядке менеджера, остальные остаются чипами.
     *
     * @param  Collection<int, Category>  $children
     * @return list<array{name: string, slug: string, products_count: int, icon: ?string, image: ?string, tile: bool}>
     */
    public function subsections(Collection $children, ?string $inheritedIcon): array
    {
        $tiles = $children
            ->sortByDesc('products_count')
            ->take(self::SUBSECTION_TILES)
            ->pluck('id')
            ->all();

        $images = $this->for($tiles);

        return $children
            ->map(fn (Category $child): array => [
                'name' => $child->name,
                'slug' => $child->slug,
                'products_count' => $child->products_count,
                'icon' => $child->icon ?? $inheritedIcon,
                'image' => $images[$child->id] ?? null,
                'tile' => in_array($child->id, $tiles, true),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function resolve(array $ids): array
    {
        $found = [];

        foreach ($this->manual($ids) as $id => $media) {
            $found[$id] = $media->hasGeneratedConversion('tile') ? $media->getUrl('tile') : $media->getUrl();
        }

        $automatic = $this->automatic(array_values(array_diff($ids, array_keys($found))));

        foreach ($automatic as $id => $media) {
            $found[$id] = $media->getUrl('card');
        }

        return $found;
    }

    /**
     * Картинки, которые загрузил менеджер.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Media>
     */
    private function manual(array $ids): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        return Media::query()
            ->where('model_type', (new Category)->getMorphClass())
            ->whereIn('model_id', $ids)
            ->where('collection_name', Category::IMAGE)
            ->orderBy('order_column')
            ->get()
            ->unique('model_id')
            ->keyBy('model_id');
    }

    /**
     * Фото ходового товара: по одному лучшему на каждый раздел-лист одним запросом, дальше
     * для каждого раздела выбирается лучшее среди листьев его ветки. Лучший — тот, что в
     * наличии и популярнее, как в сортировке «Популярные» (ТЗ §8.2).
     *
     * @param  list<int>  $ids
     * @return Collection<int, Media>
     */
    private function automatic(array $ids): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        $branches = [];

        foreach ($ids as $id) {
            $branches[$id] = $this->tree->activeBranch($id);
        }

        $leaves = array_values(array_unique(array_merge(...array_values($branches))));

        if ($leaves === []) {
            return new Collection;
        }

        $productType = (new Product)->getMorphClass();

        $ranked = $this->catalog->listed()
            ->whereIn('products.category_id', $leaves)
            ->join('media', fn (JoinClause $join) => $join
                ->on('media.model_id', '=', 'products.id')
                ->where('media.model_type', $productType)
                ->where('media.collection_name', Product::IMAGES)
                ->whereJsonContains('media.generated_conversions->card', true))
            ->toBase()
            ->selectRaw('media.id AS media_id, products.category_id, products.availability_rank, products.popularity, products.id AS product_id')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY products.category_id ORDER BY products.availability_rank, products.popularity DESC, products.id, media.order_column) AS place');

        $best = DB::query()->fromSub($ranked, 'ranked')->where('place', 1)->get()->keyBy('category_id');

        $chosen = [];

        foreach ($branches as $id => $branch) {
            $top = $best->only($branch)->sort(fn (object $a, object $b): int => [$a->availability_rank, $b->popularity, $a->product_id]
                <=> [$b->availability_rank, $a->popularity, $b->product_id])->first();

            if ($top !== null) {
                $chosen[$id] = (int) $top->media_id;
            }
        }

        $media = Media::query()->whereKey(array_values($chosen))->get()->keyBy('id');

        return collect($chosen)
            ->map(fn (int $mediaId): ?Media => $media->get($mediaId))
            ->filter();
    }
}
