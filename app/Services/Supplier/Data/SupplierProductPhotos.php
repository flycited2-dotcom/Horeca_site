<?php

namespace App\Services\Supplier\Data;

/**
 * Photos of one supplier product, in the supplier's order: the first is the main one.
 */
final readonly class SupplierProductPhotos
{
    /**
     * @param  list<string>  $urls
     */
    public function __construct(
        public string $externalId,
        public array $urls,
    ) {}
}
