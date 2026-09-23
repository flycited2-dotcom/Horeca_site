<?php

namespace App\Services\Supplier\Sources\Rosholod;

use App\Enums\ImportEntity;
use App\Models\ImportProfile;
use App\Services\Supplier\Contracts\SupplierFeedInterface;
use App\Services\Supplier\Data\FeedCapabilities;
use App\Services\Supplier\Data\FetchedSource;
use App\Services\Supplier\Data\SupplierCategory;
use App\Services\Supplier\Data\SupplierProduct;
use App\Services\Supplier\Data\SupplierRecordError;
use App\Services\Supplier\Import\PriceNormalizer;
use App\Services\Supplier\Import\SourceFetcher;
use App\Services\Supplier\Import\SupplierText;
use App\Services\Supplier\Xml\Cp1251XmlReader;
use InvalidArgumentException;

/**
 * Catalog.xml: the category tree and products with RRP (TZ §6.1).
 */
final class RosholodCatalogXmlSource implements SupplierFeedInterface
{
    public function __construct(
        private readonly SourceFetcher $fetcher,
        private readonly Cp1251XmlReader $xml,
        private readonly PriceNormalizer $prices,
    ) {}

    public function capabilities(): FeedCapabilities
    {
        return new FeedCapabilities(
            entities: [ImportEntity::Category, ImportEntity::Product],
            ownedProductFields: ['name', 'model', 'sku', 'supplier_code', 'brand_id', 'category_id', 'rrp_price'],
            isFullSnapshot: true,
            // The XML cuts descriptions at 500 characters: the full text comes with the site content (TZ §6).
            seededProductFields: ['description'],
        );
    }

    public function fetch(ImportProfile $profile, bool $force = false): ?FetchedSource
    {
        return $this->fetcher->fetch($profile, $force);
    }

    public function read(FetchedSource $source): iterable
    {
        $config = config('suppliers.rosholod.catalog');

        foreach ($this->xml->elements($source->path, [$config['category_element'], $config['product_element']]) as $element) {
            if ($element->localName === $config['category_element']) {
                yield $this->category(
                    trim($element->getAttribute('id')),
                    trim($element->getAttribute('parentId')),
                    $element->textContent,
                );

                continue;
            }

            yield $this->product(Cp1251XmlReader::childTexts($element), $config['fields']);
        }
    }

    private function category(string $externalId, string $parentExternalId, string $rawName): SupplierCategory|SupplierRecordError
    {
        $name = SupplierText::clean($rawName, 255);

        if ($externalId === '') {
            return new SupplierRecordError(ImportEntity::Category, '', __('import.records.missing_external_id'));
        }

        if ($name === null) {
            return new SupplierRecordError(ImportEntity::Category, $externalId, __('import.records.missing_name'));
        }

        Cp1251XmlReader::assertReadable($name);

        return new SupplierCategory($externalId, $parentExternalId !== '' ? $parentExternalId : null, $name);
    }

    /**
     * @param  array<string, string>  $texts
     * @param  array<string, string>  $fields
     */
    private function product(array $texts, array $fields): SupplierProduct|SupplierRecordError
    {
        $value = fn (string $field): ?string => $texts[$fields[$field]] ?? null;
        $externalId = trim((string) $value('external_id'));
        $name = SupplierText::clean($value('name'), 255);

        if ($externalId === '') {
            return new SupplierRecordError(ImportEntity::Product, '', __('import.records.missing_external_id'));
        }

        if ($name === null) {
            return new SupplierRecordError(ImportEntity::Product, $externalId, __('import.records.missing_name'));
        }

        Cp1251XmlReader::assertReadable($name);

        try {
            $price = $this->prices->parse($value('price'));
        } catch (InvalidArgumentException) {
            return new SupplierRecordError(ImportEntity::Product, $externalId, __('import.records.invalid_price', ['price' => $value('price')]));
        }

        if ($price?->isNegative()) {
            return new SupplierRecordError(ImportEntity::Product, $externalId, __('import.records.negative_price', ['price' => $value('price')]));
        }

        return new SupplierProduct(
            externalId: $externalId,
            name: $name,
            supplierCode: SupplierText::clean($value('supplier_code'), 64),
            sku: SupplierText::clean($value('sku'), 64),
            model: SupplierText::clean($value('model'), 255),
            description: SupplierText::clean($value('description')),
            brandName: SupplierText::clean($value('brand'), 150),
            categoryName: SupplierText::clean($value('category'), 255),
            rrpPrice: $price,
        );
    }
}
