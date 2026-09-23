<?php

namespace App\Services\Supplier\Data;

/**
 * One characteristic of a supplier product as the supplier writes it (TZ §6.2):
 * «Мощность, Вт» => «1 300». Parsing into name, unit and number is AttributeValueParser's job.
 */
final readonly class SupplierAttribute
{
    public function __construct(
        public string $key,
        public string $rawValue,
    ) {}
}
