<?php

namespace App\View;

use App\Enums\Availability;
use App\Models\Product;
use App\Services\Pricing\Price;
use App\Support\CategoryZone;

/**
 * Полка товаров главной в облике «Свечение» (ТЗ §8.1): лента «В наличии», «Готово к отгрузке»,
 * «Часто заказывают» или «Новинки» в виде готовых к показу строк. Бренд, категория и фото
 * приходят вместе с товарами (CatalogQuery::withCardData), цены — от PriceResolver: полка
 * не делает запросов и ничего не считает сама. Из трёх товаров и больше «В наличии» превращается
 * в гармошку, меньше — остаётся карточками.
 */
final readonly class HomeShelf
{
    /**
     * Сколько товаров нужно, чтобы лента «В наличии» стала гармошкой: из одной-двух колонок гармошка не складывается.
     */
    public const int ACCORDION_MIN = 3;

    /**
     * @param  list<array{product: Product, brand: ?string, name: string, lead: ?string, rest: string, image: ?string, icon: ?string, zone: string, availability: Availability, price: ?Price}>  $items
     */
    public function __construct(public array $items) {}

    /**
     * @param  iterable<int, Product>  $products
     * @param  array<int, Price|null>  $prices  id товара => цена, как отдаёт PriceResolver::forMany()
     */
    public static function from(iterable $products, array $prices): self
    {
        $items = [];

        foreach ($products as $product) {
            $items[] = self::item($product, $prices[$product->id] ?? null);
        }

        return new self($items);
    }

    public function isAccordion(): bool
    {
        return count($this->items) >= self::ACCORDION_MIN;
    }

    /**
     * @return array{product: Product, brand: ?string, name: string, lead: ?string, rest: string, image: ?string, icon: ?string, zone: string, availability: Availability, price: ?Price}
     */
    private static function item(Product $product, ?Price $price): array
    {
        $name = $product->name;
        $model = trim((string) $product->model);

        // Модель в начале названия — машинные данные: в карточке она набирается моноширинным шрифтом.
        $lead = $model !== '' && str_starts_with($name, $model.' ') ? $model : null;

        return [
            'product' => $product,
            'brand' => $product->brand?->name,
            'name' => $name,
            'lead' => $lead,
            'rest' => $lead === null ? $name : mb_substr($name, mb_strlen($lead) + 1),
            'image' => $product->getFirstMediaUrl(Product::IMAGES, 'card') ?: null,
            'icon' => $product->category?->icon,
            'zone' => CategoryZone::of($product->category?->icon, $product->category?->name),
            'availability' => $product->availability,
            'price' => $price,
        ];
    }
}
