<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 999_999);
        $minDays = fake()->numberBetween(2, 8);

        return [
            'supplier_id' => Supplier::factory(),
            'name' => "Склад {$number}",
            'slug' => "warehouse-{$number}",
            'city' => fake()->city(),
            'delivery_days_min' => $minDays,
            'delivery_days_max' => $minDays + fake()->numberBetween(1, 4),
            'is_visible' => true,
            'sort' => 0,
        ];
    }
}
