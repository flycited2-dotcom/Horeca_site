<?php

namespace App\Services\Supplier\Data;

use App\Enums\ImportEntity;

final readonly class FeedCapabilities
{
    /**
     * Product fields a source can own. "stocks" stands for the product's warehouse stock records.
     */
    public const array PRODUCT_FIELDS = [
        'name', 'model', 'sku', 'supplier_code', 'description', 'brand_id', 'category_id',
        'rrp_price', 'purchase_price', 'unit', 'stocks',
    ];

    /**
     * @param  list<ImportEntity>  $entities
     * @param  list<string>  $ownedProductFields
     * @param  list<string>  $seededProductFields  fields the source fills only in a product it creates:
     *                                             another source owns them afterwards
     */
    public function __construct(
        public array $entities,
        public array $ownedProductFields,
        public bool $isFullSnapshot,
        public array $seededProductFields = [],
    ) {}

    public function provides(ImportEntity $entity): bool
    {
        return in_array($entity, $this->entities, true);
    }

    public function owns(string $field): bool
    {
        return in_array($field, $this->ownedProductFields, true);
    }

    public function seeds(string $field): bool
    {
        return in_array($field, $this->seededProductFields, true);
    }
}
