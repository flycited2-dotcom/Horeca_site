<?php

namespace Database\Factories;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $total = Money::ofRubles(fake()->numberBetween(10_000, 900_000));

        return [
            'number' => 'HR-'.now()->format('ymd').'-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'idempotency_key' => (string) Str::uuid(),
            'user_id' => null,
            'company_id' => null,
            'type' => OrderType::Retail,
            'status' => OrderStatus::New,
            'customer_name' => fake()->name(),
            'phone' => '+7978'.fake()->numerify('#######'),
            'email' => fake()->safeEmail(),
            'is_legal_entity' => false,
            'delivery_method' => DeliveryMethod::Pickup,
            'payment_method' => PaymentMethod::Cash,
            'subtotal' => $total,
            'discount' => Money::zero(),
            'total' => $total,
        ];
    }
}
