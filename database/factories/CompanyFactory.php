<?php

namespace Database\Factories;

use App\Enums\CompanySegment;
use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\PriceTier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legal_name' => fake()->company(),
            'brand_name' => null,
            'inn' => fake()->numerify('91########'),
            'kpp' => fake()->numerify('9102#####'),
            'ogrn' => fake()->numerify('#############'),
            'legal_address' => fake()->address(),
            'delivery_address' => null,
            'city' => 'Симферополь',
            'contact_person' => fake()->name(),
            'phone' => '+7978'.fake()->numerify('#######'),
            'email' => fake()->unique()->companyEmail(),
            'segment' => fake()->randomElement(CompanySegment::cases()),
            'status' => CompanyStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CompanyStatus::Approved,
            'price_tier_id' => PriceTier::factory(),
            'approved_at' => now(),
        ]);
    }
}
