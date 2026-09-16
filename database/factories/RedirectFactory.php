<?php

namespace Database\Factories;

use App\Models\Redirect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Redirect>
 */
class RedirectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'from_path' => '/old/'.fake()->unique()->numberBetween(1, 999_999),
            'to_path' => '/catalog',
            'status_code' => 301,
        ];
    }
}
