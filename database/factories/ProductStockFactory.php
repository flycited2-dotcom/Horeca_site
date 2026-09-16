<?php

namespace Database\Factories;

use App\Enums\WarehouseStockStatus;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductStock>
 */
class ProductStockFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'warehouse_id' => Warehouse::factory(),
            'status' => WarehouseStockStatus::InStock,
            'quantity' => null,
            'raw_value' => 'в наличии',
            'unit' => 'шт',
            'synced_at' => now(),
        ];
    }
}
