<?php

namespace App\Services\Supplier\Data;

/**
 * What a supplier source tells about a product beyond the price list (TZ §6): the full
 * description, dimensions, weight, warranty and characteristics. null means «the source says
 * nothing» — such a field is left as it is. The weight is a decimal string, never a float.
 */
final readonly class SupplierProductDetails
{
    /**
     * @param  list<SupplierAttribute>  $attributes
     */
    public function __construct(
        public string $externalId,
        public ?string $description = null,
        public array $attributes = [],
        public ?int $lengthMm = null,
        public ?int $widthMm = null,
        public ?int $heightMm = null,
        public ?string $weightKg = null,
        public ?int $warrantyMonths = null,
    ) {}
}
