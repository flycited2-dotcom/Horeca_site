<?php

namespace App\Actions\Seo;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Services\Catalog\CatalogQuery;
use DateTimeInterface;
use XMLWriter;

/**
 * Карта сайта (ТЗ §14): главная, каталог, бренды, заявка на опт, включённые разделы,
 * товары витрины (видимые, не снятые, из включённых разделов), бренды с товарами и
 * включённые страницы. Пишется потоком во временный файл и подменяет прежний целиком —
 * поисковик никогда не получит половину карты. Строится ежедневно в 04:00.
 */
final class GenerateSitemap
{
    public function __construct(private readonly CatalogQuery $catalog) {}

    public static function path(): string
    {
        return storage_path('app/sitemap.xml');
    }

    /**
     * @return int how many addresses the map lists
     */
    public function handle(): int
    {
        $temporary = self::path().'.tmp';
        $xml = new XMLWriter;
        $xml->openUri($temporary);
        $xml->setIndent(false);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        $count = 0;
        $add = function (string $url, ?DateTimeInterface $modified = null) use ($xml, &$count): void {
            $xml->startElement('url');
            $xml->writeElement('loc', $url);

            if ($modified !== null) {
                $xml->writeElement('lastmod', $modified->format('Y-m-d'));
            }

            $xml->endElement();
            $count++;
        };

        foreach (['home', 'catalog', 'brands', 'wholesale'] as $route) {
            $add(route($route));
        }

        Category::query()->active()->select(['id', 'slug', 'updated_at'])
            ->lazyById(500)
            ->each(fn (Category $category) => $add(route('category', $category), $category->updated_at));

        $this->catalog->listed()->select(['id', 'slug', 'updated_at'])
            ->lazyById(1000)
            ->each(fn (Product $product) => $add(route('product', $product), $product->updated_at));

        foreach ($this->catalog->brandDirectory() as $brand) {
            $add(route('brand', $brand['slug']));
        }

        Page::query()->where('is_active', true)->orderBy('sort')->get(['slug', 'updated_at'])
            // «Оптовикам» is the wholesale page, already listed.
            ->reject(fn (Page $page): bool => $page->url() === route('wholesale'))
            ->each(fn (Page $page) => $add($page->url(), $page->updated_at));

        $xml->endElement();
        $xml->endDocument();
        $xml->flush();

        rename($temporary, self::path());

        return $count;
    }
}
