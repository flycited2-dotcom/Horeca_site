<?php

namespace Database\Factories;

use App\Enums\LeadStatus;
use App\Enums\LeadType;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => LeadType::Callback,
            'name' => fake()->firstName(),
            'phone' => '+7978'.fake()->numerify('#######'),
            'email' => null,
            'product_id' => null,
            'message' => null,
            'status' => LeadStatus::New,
        ];
    }
}
