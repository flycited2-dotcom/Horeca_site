<?php

namespace App\Actions\Seo;

use App\Enums\Availability;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\CatalogQuery;
use App\Services\Seo\MetaBuilder;
use App\Support\SeoUrl;
use XMLWriter;

final class YandexFeed
{
    public function __construct(
        private readonly CatalogQuery $catalog,
        private readonly MetaBuilder $meta,
    ) {}

    public function stream(): void
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('yml_catalog');
        $xml->writeAttribute('date', now()->toIso8601String());
        $xml->startElement('shop');
        $xml->writeElement('name', $this->meta->site());
        $xml->writeElement('company', $this->meta->site());
        $xml->writeElement('url', SeoUrl::canonical(route('home')));

        $xml->startElement('currencies');
        $xml->startElement('currency');
        $xml->writeAttribute('id', 'RUR');
        $xml->writeAttribute('rate', '1');
        $xml->endElement();
        $xml->endElement();

        $categories = Category::query()->active()->get(['id', 'parent_id', 'name'])->keyBy('id');
        $xml->startElement('categories');
        foreach ($categories as $category) {
            $xml->startElement('category');
            $xml->writeAttribute('id', (string) $category->id);
            if ($categories->has($category->parent_id)) {
                $xml->writeAttribute('parentId', (string) $category->parent_id);
            }
            $xml->text($category->name);
            $xml->endElement();
        }
        $xml->endElement();

        $xml->startElement('offers');
        echo $xml->flush();

        $products = $this->catalog->listed()->where('retail_price', '>', 0)->with(['brand', 'media']);
        $products->chunkById(500, function ($batch) use ($xml, $categories): void {
            foreach ($batch as $product) {
                if ($categories->has($product->category_id)) {
                    $this->writeOffer($xml, $product);
                }
            }

            echo $xml->flush();
        });

        $xml->endElement();
        $xml->endElement();
        $xml->endElement();
        $xml->endDocument();
        echo $xml->flush();
    }

    private function writeOffer(XMLWriter $xml, Product $product): void
    {
        $picture = $product->getFirstMediaUrl(Product::IMAGES);
        $description = trim(strip_tags((string) $product->description));
        if ($picture === '' || $description === '') {
            return;
        }

        $xml->startElement('offer');
        $xml->writeAttribute('id', (string) $product->id);
        $xml->writeAttribute('available', in_array($product->availability, [Availability::InStock, Availability::Low], true) ? 'true' : 'false');
        $xml->writeElement('name', mb_substr($product->name, 0, 150));
        $xml->writeElement('url', SeoUrl::canonical(route('product', $product)));
        $xml->writeElement('price', $product->retail_price->toDecimal());
        $xml->writeElement('currencyId', 'RUR');
        $xml->writeElement('categoryId', (string) $product->category_id);
        $xml->writeElement('picture', url($picture));
        if ($product->brand) {
            $xml->writeElement('vendor', $product->brand->name);
        }
        $xml->writeElement('description', mb_substr($description, 0, 3000));
        $xml->endElement();
    }
}
