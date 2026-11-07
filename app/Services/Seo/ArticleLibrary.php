<?php

namespace App\Services\Seo;

use App\Models\Brand;
use App\Models\Category;
use App\Services\Catalog\CatalogQuery;
use App\Support\SeoUrl;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

final class ArticleLibrary
{
    private ?Collection $articles = null;

    public static function url(?string $slug = null): string
    {
        $path = $slug === null ? route('articles.index') : route('articles.show', $slug);

        return rtrim(SeoUrl::canonical($path), '/').'/';
    }

    public function all(): Collection
    {
        $index = config('seo.articles_index');

        return $this->articles ??= collect(File::isFile($index) ? File::json($index, JSON_THROW_ON_ERROR) : []);
    }

    public function related(array $article): Collection
    {
        return $this->all()->except($article['slug'])
            ->sortBy(fn (array $other): bool => $this->categoryKey($article['category']) !== $this->categoryKey($other['category']))
            ->take(5);
    }

    public function forCategory(?Category $category): Collection
    {
        $articles = $this->all();
        if ($articles->isEmpty() || ! ($category?->is_active)) {
            return collect();
        }

        $keys = [];
        $paths = [];
        for ($node = $category; $node && $node->is_active; $node = $node->parent) {
            $keys[] = $this->categoryKey($node->name);
            $keys[] = $this->categoryKey($node->slug);
            $paths[] = 'catalog/'.$node->slug;
        }

        return $articles->filter(fn (array $article): bool => in_array($this->categoryKey($article['category']), $keys, true)
            || array_intersect($paths, array_map(fn (string $link): string => trim($link, '/'), $article['links'])) !== []
        )->take(5);
    }

    public function catalogLinks(array $article, CatalogQuery $catalog): array
    {
        $links = [];
        foreach (array_unique(array_map(fn (string $path): string => trim($path, '/'), $article['links'])) as $path) {
            [$group, $slug] = array_pad(explode('/', $path, 2), 2, null);
            if ($slug === null) {
                $links['/'.$path] = __($group === 'catalog' ? 'shop.layout.catalog' : 'shop.brands.title');

                continue;
            }

            $query = match ($group) {
                'catalog' => Category::query()->active(),
                'brands' => Brand::query()->where('is_active', true),
                'product' => $catalog->listed(),
            };

            $name = $query->where('slug', $slug)->value('name');
            if ($name !== null) {
                $links['/'.$path] = $name;
            }
            if (count($links) === 3) {
                break;
            }
        }

        return array_slice($links + ['/catalog' => __('shop.layout.catalog')], 0, 3, true);
    }

    private function categoryKey(string $category): string
    {
        return str_replace('ё', 'е', mb_strtolower(trim($category)));
    }
}
