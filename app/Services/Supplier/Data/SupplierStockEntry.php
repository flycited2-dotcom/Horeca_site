<?php

namespace App\Services\Supplier\Data;

use App\Enums\WarehouseStockStatus;

final readonly class SupplierStockEntry
{
    public function __construct(
        public string $warehouseName,
        public WarehouseStockStatus $status,
        public ?string $rawValue = null,
        public ?string $quantity = null,
        public ?string $warning = null,
    ) {}
}
