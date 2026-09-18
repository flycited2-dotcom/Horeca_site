<?php

namespace App\Services\Supplier\Sync;

use App\Services\Supplier\Import\ImportLog;
use App\Services\Supplier\Import\SupplierText;

/**
 * Finds the storefront category of a product.
 *
 * The XML catalog names the subcategory instead of giving its GUID (TZ §6.1, fact 5),
 * so both ways of pointing at a category are supported.
 */
final readonly class CategoryMap
{
    /**
     * @param  array<string, int|null>  $byKey  supplier key => storefront category id
     * @param  array<string, string>  $byName  matching name => supplier key
     */
    public function __construct(
        private array $byKey,
        private array $byName,
    ) {}

    public function resolve(?string $externalId, ?string $name, ImportLog $log): ?int
    {
        if ($externalId !== null && array_key_exists($externalId, $this->byKey)) {
            return $this->byKey[$externalId];
        }

        if ($name === null) {
            return null;
        }

        $key = SupplierText::key($name);

        if (! isset($this->byName[$key])) {
            $log->addOnce("category-unknown:{$key}", __('import.records.unknown_category', ['name' => $name]));

            return null;
        }

        return $this->byKey[$this->byName[$key]] ?? null;
    }
}
