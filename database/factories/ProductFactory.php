<?php

namespace Database\Factories;

use App\Enums\Availability;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 9_999_999);
        $type = fake()->randomElement(['Пароконвектомат', 'Холодильный шкаф', 'Плита индукционная', 'Стол производственный']);
        $model = 'ПКА '.fake()->numberBetween(6, 20).'-1/1';
        $price = Money::ofRubles(fake()->numberBetween(5_000, 600_000));

        return [
            'supplier_id' => Supplier::factory(),
            'external_id' => fake()->uuid(),
            'supplier_code' => 'ЦБ-Ц'.str_pad((string) $number, 7, '0', STR_PAD_LEFT),
            'sku' => fake()->numerify('###########'),
            'model' => $model,
            'name' => "{$type} {$model}",
            'slug' => "product-{$number}",
            'category_id' => Category::factory(),
            'brand_id' => Brand::factory(),
            'description' => fake()->realText(200),
            'unit' => 'шт',
            'rrp_price' => $price,
            'retail_price' => $price,
            'availability' => Availability::OnOrder,
            'is_visible' => true,
        ];
    }

    public function withAvailability(Availability $availability): static
    {
        return $this->state(fn (array $attributes) => [
            'availability' => $availability,
        ]);
    }

    public function inStock(): static
    {
        return $this->withAvailability(Availability::InStock);
    }

    public function discontinued(): static
    {
        return $this->withAvailability(Availability::Discontinued);
    }

    public function priceOnRequest(): static
    {
        return $this->state(fn (array $attributes) => [
            'rrp_price' => null,
            'retail_price' => null,
        ]);
    }
}
