<?php

namespace Database\Factories;

use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductPrice>
 */
class ProductPriceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'price_tier_id' => PriceTier::factory(),
            'price' => Money::ofRubles(fake()->numberBetween(4_000, 500_000)),
        ];
    }
}
