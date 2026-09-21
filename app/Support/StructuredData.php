<?php

namespace App\Support;

use App\Models\Product;
use App\Services\Pricing\Price;

/**
 * schema.org markup of the storefront pages (TZ §8.3, §14).
 */
final class StructuredData
{
    /**
     * Product with its Offer: price in rubles, availability by TZ §6.5. A product with the
     * price on request gets no Offer — search engines would take an empty price for zero.
     *
     * @return array<string, mixed>
     */
    public static function product(Product $product, ?Price $price, ?string $image = null): array
    {
        $data = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'sku' => $product->sku,
            'mpn' => $product->model,
            'image' => $image,
            'description' => $product->description !== null ? mb_substr(trim(strip_tags($product->description)), 0, 500) : null,
            'brand' => $product->brand ? ['@type' => 'Brand', 'name' => $product->brand->name] : null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        if ($price !== null) {
            $data['offers'] = [
                '@type' => 'Offer',
                'url' => route('product', $product),
                'price' => $price->amount->toDecimal(),
                'priceCurrency' => 'RUB',
                'availability' => $product->availability->schemaOrg(),
            ];
        }

        return $data;
    }

    /**
     * JSON for a <script type="application/ld+json">: «</script>» inside a name can not close
     * the tag, Cyrillic stays readable.
     *
     * @param  array<string, mixed>  $data
     */
    public static function json(array $data): string
    {
        return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
    }
}
