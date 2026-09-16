<?php

namespace Database\Factories;

use App\Enums\Availability;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $qty = fake()->numberBetween(1, 3);
        $price = Money::ofRubles(fake()->numberBetween(5_000, 300_000));

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'sku' => fake()->numerify('###########'),
            'supplier_code' => 'ЦБ-Ц'.fake()->numerify('#######'),
            'name' => 'Плита индукционная КИП-'.fake()->numberBetween(20, 60).'Н',
            'unit' => 'шт',
            'availability' => Availability::OnOrder,
            'qty' => $qty,
            'price' => $price,
            'sum' => $price->multiply($qty),
        ];
    }
}
