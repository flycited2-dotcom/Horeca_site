<?php

namespace App\Services\Supplier\Data;

final readonly class SupplierCategory
{
    public function __construct(
        public string $externalId,
        public ?string $parentExternalId,
        public string $name,
    ) {}
}
