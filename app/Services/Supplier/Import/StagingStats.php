<?php

namespace App\Services\Supplier\Import;

use App\Enums\ImportEntity;

/**
 * What one pass over the source produced.
 *
 * Brands and warehouses have no staging entity of their own, so their names are
 * collected while the records are written: the staging table is read only once.
 */
final class StagingStats
{
    /**
     * @var array<string, array{total: int, valid: int, invalid: int}>
     */
    private array $counts = [];

    /**
     * @var array<string, string> matching key => supplier name
     */
    private array $brands = [];

    /**
     * @var array<string, string> matching key => supplier name
     */
    private array $warehouses = [];

    public function count(ImportEntity $entity, bool $isValid): void
    {
        $counts = $this->counts[$entity->value] ?? ['total' => 0, 'valid' => 0, 'invalid' => 0];

        $counts['total']++;
        $counts[$isValid ? 'valid' : 'invalid']++;

        $this->counts[$entity->value] = $counts;
    }

    public function rememberBrand(string $name): void
    {
        $this->brands[SupplierText::key($name)] ??= $name;
    }

    public function rememberWarehouse(string $name): void
    {
        $this->warehouses[SupplierText::key($name)] ??= $name;
    }

    /**
     * @return array<string, string>
     */
    public function brands(): array
    {
        return $this->brands;
    }

    /**
     * @return array<string, string>
     */
    public function warehouses(): array
    {
        return $this->warehouses;
    }

    public function total(): int
    {
        return $this->sum('total');
    }

    public function valid(): int
    {
        return $this->sum('valid');
    }

    public function invalid(): int
    {
        return $this->sum('invalid');
    }

    private function sum(string $key): int
    {
        return array_sum(array_column($this->counts, $key));
    }
}
