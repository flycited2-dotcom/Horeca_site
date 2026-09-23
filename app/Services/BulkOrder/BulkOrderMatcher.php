<?php

namespace App\Services\BulkOrder;

use App\Enums\Availability;
use App\Models\Product;
use App\Models\User;
use App\Services\Cart\CartBlock;
use App\Services\Cart\CartRules;
use App\Services\Catalog\CatalogQuery;
use App\Services\Pricing\Price;
use App\Services\Pricing\PriceResolver;
use App\Services\Search\SearchTextBuilder;

/**
 * Сопоставление заказа списком с каталогом (ТЗ §11): по сжатому артикулу, а если по нему
 * ничего нет — по коду 1С, как точное совпадение поиска. Весь список — одним запросом.
 * Артикул у поставщика повторяется, поэтому одна строка может указать на несколько товаров:
 * тогда клиент выбирает сам. Цены — для этого клиента, на момент показа.
 */
final class BulkOrderMatcher
{
    public function __construct(
        private readonly CatalogQuery $catalog,
        private readonly PriceResolver $prices,
        private readonly CartRules $rules,
    ) {}

    /**
     * @param  list<BulkOrderLine>  $lines
     * @return list<BulkOrderMatch>
     */
    public function match(array $lines, ?User $user): array
    {
        $codes = [];

        foreach ($lines as $line) {
            if ($line->error === null) {
                $codes[] = SearchTextBuilder::compact($line->sku);
            }
        }

        $products = $this->catalog->byCompactCodes(array_values(array_unique(array_filter($codes))), $user);
        $prices = $this->prices->forMany($products, $user);

        $bySku = [];
        $byCode = [];

        foreach ($products as $product) {
            $bySku[SearchTextBuilder::compact($product->sku)][] = $product;
            $byCode[SearchTextBuilder::compact($product->supplier_code)][] = $product;
        }

        return array_map(function (BulkOrderLine $line) use ($bySku, $byCode, $prices): BulkOrderMatch {
            if ($line->error !== null) {
                return new BulkOrderMatch($line, BulkOrderStatus::Invalid);
            }

            $code = SearchTextBuilder::compact($line->sku);
            $candidates = $code === '' ? [] : ($bySku[$code] ?? $byCode[$code] ?? []);
            $own = array_intersect_key($prices, array_flip(array_map(fn (Product $product): int => $product->id, $candidates)));
            $buyable = array_values(array_map(
                fn (Product $product): int => $product->id,
                array_filter($candidates, fn (Product $product): bool => $this->rules->block($product, $prices[$product->id] ?? null) === null),
            ));

            return new BulkOrderMatch($line, $this->status($candidates, $prices), $candidates, $own, $buyable);
        }, $lines);
    }

    /**
     * @param  list<Product>  $candidates
     * @param  array<int, Price|null>  $prices
     */
    private function status(array $candidates, array $prices): BulkOrderStatus
    {
        if ($candidates === []) {
            return BulkOrderStatus::NotFound;
        }

        if (count($candidates) > 1) {
            return BulkOrderStatus::Multiple;
        }

        $product = $candidates[0];

        return match ($this->rules->block($product, $prices[$product->id] ?? null)) {
            null => BulkOrderStatus::Found,
            CartBlock::PriceOnRequest => BulkOrderStatus::PriceOnRequest,
            CartBlock::Discontinued, CartBlock::Hidden => $product->availability === Availability::Discontinued
                ? BulkOrderStatus::Discontinued
                : BulkOrderStatus::NotFound,
        };
    }
}
