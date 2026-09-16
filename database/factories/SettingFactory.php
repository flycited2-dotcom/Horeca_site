<?php

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'test.setting_'.fake()->unique()->numberBetween(1, 999_999),
            'value' => fake()->numberBetween(1, 100),
        ];
    }
}
