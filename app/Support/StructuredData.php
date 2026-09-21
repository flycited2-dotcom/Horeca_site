<?php

namespace App\Support;

use App\Models\Product;
use App\Services\Pricing\Price;
use App\View\StorefrontShell;

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
     * The shop as an Organization (TZ §14) on the home page: only the contacts the customer
     * has filled in the settings.
     *
     * @return array<string, mixed>
     */
    public static function organization(StorefrontShell $shell): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $shell->siteName,
            'url' => url('/'),
            'telephone' => $shell->phone()['label'] ?? null,
            'email' => $shell->email,
            'address' => $shell->address,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
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
