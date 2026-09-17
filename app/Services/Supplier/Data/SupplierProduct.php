<?php

namespace App\Services\Supplier\Data;

use App\Support\Money;

/**
 * A supplier product. The category is referenced by GUID when the source has one,
 * otherwise by name (the XML catalog puts the subcategory name into categoryId).
 */
final readonly class SupplierProduct
{
    public function __construct(
        public string $externalId,
        public string $name,
        public ?string $supplierCode = null,
        public ?string $sku = null,
        public ?string $model = null,
        public ?string $description = null,
        public ?string $brandName = null,
        public ?string $categoryExternalId = null,
        public ?string $categoryName = null,
        public ?Money $rrpPrice = null,
        public ?Money $purchasePrice = null,
    ) {}
}
