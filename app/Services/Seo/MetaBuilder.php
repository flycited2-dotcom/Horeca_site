<?php

namespace App\Services\Seo;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Services\Catalog\CatalogFilters;
use App\Services\Catalog\CatalogSort;
use App\Services\Settings\Settings;
use App\Support\Typography;
use Illuminate\Support\Str;

/**
 * Заголовки и описания для поисковиков (ТЗ §14). Что менеджер вписал в `meta_*` товара,
 * раздела или страницы — главное; пустое заполняется по шаблонам из «Настроек»:
 * товар — «{name} — купить в Симферополе, цена {price} | {site}» (без цены, если она по
 * запросу), раздел — «{name} — купить в Симферополе | {site}». Цена в заголовке — розничная:
 * её видят гость и поисковик. Листинг с несколькими фильтрами или с фильтром и сортировкой
 * закрыт от индекса (noindex, follow), каноническим считается адрес без служебных меток.
 */
final class MetaBuilder
{
    public const int DESCRIPTION_LENGTH = 160;

    private const string PRODUCT_TEMPLATE = '{name} — купить в Симферополе, цена {price} | {site}';

    private const string CATEGORY_TEMPLATE = '{name} — купить в Симферополе | {site}';

    public function __construct(private readonly Settings $settings) {}

    public function product(Product $product): Meta
    {
        $price = $product->retail_price !== null ? Typography::money($product->retail_price) : null;

        return new Meta(
            title: $this->filled($product->meta_title) ?? $this->render($this->template('seo.product_title_template', self::PRODUCT_TEMPLATE), $product->name, $price),
            description: $this->filled($product->meta_description) ?? $this->limit(implode(' ', array_filter([
                __('shop.seo.product_description', [
                    'name' => $product->name,
                    'price' => $price ?? mb_strtolower(__('shop.price.on_request')),
                    'availability' => mb_strtolower($product->availability->getLabel()),
                ]),
                $product->brand?->name ? __('shop.seo.product_brand', ['brand' => $product->brand->name]) : null,
                filled($product->sku) ? __('shop.seo.product_sku', ['sku' => $product->sku]) : null,
            ]))),
            canonical: route('product', $product),
        );
    }

    public function category(Category $category, CatalogFilters $filters, int $page = 1): Meta
    {
        return new Meta(
            title: $this->filled($category->meta_title) ?? $this->render($this->template('seo.category_title_template', self::CATEGORY_TEMPLATE), $category->h1 ?: $category->name, null),
            description: $this->filled($category->meta_description) ?? $this->limit(__('shop.seo.category_description', [
                'name' => $category->h1 ?: $category->name,
                'models' => trans_choice('shop.catalog.models', $category->products_count, ['count' => Typography::number($category->products_count)]),
                'site' => $this->site(),
            ])),
            canonical: $this->listingUrl(route('category', $category), $filters, $page),
            robots: $this->listingRobots($filters),
        );
    }

    public function brand(Brand $brand, CatalogFilters $filters, int $page = 1): Meta
    {
        return new Meta(
            title: __('shop.brands.meta_title', ['brand' => $brand->name]).' | '.$this->site(),
            description: $this->limit($this->filled($brand->description) ?? __('shop.seo.brand_description', ['brand' => $brand->name, 'site' => $this->site()])),
            canonical: $this->listingUrl(route('brand', $brand), $filters, $page),
            robots: $this->listingRobots($filters),
        );
    }

    public function page(Page $page): Meta
    {
        $text = trim(strip_tags((string) Str::markdown((string) $page->content, ['html_input' => 'strip'])));

        return new Meta(
            title: ($this->filled($page->meta_title) ?? $page->title).' | '.$this->site(),
            description: $this->filled($page->meta_description) ?? ($text !== '' ? $this->limit($text) : null),
            canonical: $page->url(),
        );
    }

    public function site(): string
    {
        return $this->filled($this->settings->get('site.name')) ?? (string) config('app.name');
    }

    /**
     * Several filters, or a filter with a sort order, make endless combinations of one listing:
     * robots follow the links but do not index the page (TZ §14).
     */
    private function listingRobots(CatalogFilters $filters): ?string
    {
        $count = $filters->activeCount();

        return $count > 1 || ($count > 0 && $filters->sort !== CatalogSort::Popular) ? 'noindex, follow' : null;
    }

    /**
     * The listing as it is, without the view switch and the UTM marks: a page of a listing is
     * its own canonical address (TZ §14).
     */
    private function listingUrl(string $base, CatalogFilters $filters, int $page): string
    {
        $query = $filters->toQuery() + ($page > 1 ? ['page' => $page] : []);

        return $query === [] ? $base : $base.'?'.http_build_query($query);
    }

    private function template(string $key, string $default): string
    {
        return $this->filled($this->settings->get($key)) ?? $default;
    }

    private function render(string $template, string $name, ?string $price): string
    {
        if ($price === null) {
            // «…, цена {price}» goes away entirely when the price is on request.
            $template = (string) preg_replace('/[,;]?\s*цена\s*\{price\}/iu', '', $template);
        }

        $title = strtr($template, ['{name}' => $name, '{price}' => (string) $price, '{site}' => $this->site()]);

        return trim((string) preg_replace('/[ \t\r\n]+/', ' ', $title));
    }

    private function limit(string $text): string
    {
        // Only ordinary spaces are collapsed: a price keeps its non-breaking ones.
        $text = trim((string) preg_replace('/[ \t\r\n]+/', ' ', $text));

        return Str::limit($text, self::DESCRIPTION_LENGTH, '…', preserveWords: true);
    }

    private function filled(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
