<?php

namespace Database\Factories;

use App\Enums\SupplierRefEntity;
use App\Models\Supplier;
use App\Models\SupplierRef;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierRef>
 */
class SupplierRefFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'entity' => SupplierRefEntity::Category,
            'external_key' => fake()->unique()->uuid(),
            'name' => 'Категория поставщика '.fake()->numberBetween(1, 999),
            'parent_key' => null,
            'local_id' => null,
            'is_ignored' => false,
            'last_seen_at' => now(),
        ];
    }
}
