<?php

namespace Database\Factories;

use App\Models\ProductCollection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductCollection>
 */
class ProductCollectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 999_999);

        return [
            'name' => "Подборка {$number}",
            'slug' => "collection-{$number}",
            'description' => fake()->realText(120),
            'icon' => null,
            'is_active' => true,
            'sort' => 0,
        ];
    }
}
