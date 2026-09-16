<?php

namespace Database\Factories;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 999_999);

        return [
            'name' => "Бренд {$number}",
            'slug' => "brand-{$number}",
            'country' => 'Россия',
            'description' => null,
            'is_active' => true,
        ];
    }
}
