<?php

namespace App\Services\Supplier\Import;

use App\Enums\WarehouseStockStatus;

/**
 * Turns a warehouse balance into a stock status (TZ §6.5).
 */
final class StockValueMapper
{
    /**
     * @param  array<string, string>  $textValues  lower-case text => WarehouseStockStatus value
     */
    public function __construct(private readonly array $textValues) {}

    /**
     * Maps a text balance. Unknown values count as "out" and are reported as not recognized.
     *
     * @return array{0: WarehouseStockStatus, 1: bool} status and whether the value was recognized
     */
    public function fromText(?string $value): array
    {
        $key = mb_strtolower(trim((string) $value));

        if ($key === '') {
            return [WarehouseStockStatus::Out, true];
        }

        if (array_key_exists($key, $this->textValues)) {
            return [WarehouseStockStatus::from($this->textValues[$key]), true];
        }

        return [WarehouseStockStatus::Out, false];
    }

    /**
     * Maps a numeric quantity from the supplier API against the "low stock" threshold.
     */
    public function fromQuantity(string $quantity, int $lowThreshold): WarehouseStockStatus
    {
        $value = (float) $quantity;

        return match (true) {
            $value <= 0 => WarehouseStockStatus::Out,
            $value < $lowThreshold => WarehouseStockStatus::Low,
            default => WarehouseStockStatus::InStock,
        };
    }
}
