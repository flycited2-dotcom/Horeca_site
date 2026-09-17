<?php

namespace App\Services\Supplier\Data;

/**
 * All warehouse stocks of one product in a stock snapshot.
 */
final readonly class SupplierProductStock
{
    /**
     * @param  list<SupplierStockEntry>  $entries
     */
    public function __construct(
        public string $productExternalId,
        public array $entries,
        public ?string $unit = null,
    ) {}
}
