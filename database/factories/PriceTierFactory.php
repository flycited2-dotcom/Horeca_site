<?php

namespace Database\Factories;

use App\Models\PriceTier;
use App\Support\Money;
use App\Support\Percent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceTier>
 */
class PriceTierFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 999_999);

        return [
            'name' => "Опт-{$number}",
            'slug' => "tier-{$number}",
            'discount_percent' => Percent::ofBasisPoints(fake()->randomElement([500, 1000, 1500, 2000])),
            'min_order_amount' => Money::zero(),
            'is_default' => false,
            'sort' => 0,
        ];
    }
}
