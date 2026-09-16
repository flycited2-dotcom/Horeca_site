<?php

namespace Database\Factories;

use App\Enums\PriceKind;
use App\Models\Supplier;
use App\Support\Percent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 999_999);

        return [
            'name' => "Поставщик {$number}",
            'slug' => "supplier-{$number}",
            'price_kind' => PriceKind::Rrp,
            'markup_percent' => Percent::zero(),
            'retail_round_to' => 1,
            'config' => null,
            'is_active' => true,
        ];
    }
}
