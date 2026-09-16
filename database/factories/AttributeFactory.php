<?php

namespace Database\Factories;

use App\Enums\AttributeType;
use App\Models\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attribute>
 */
class AttributeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 999_999);

        return [
            'name' => "Характеристика {$number}",
            'slug' => "attribute-{$number}",
            'unit' => 'мм',
            'type' => AttributeType::Number,
            'is_filterable' => false,
            'is_main' => false,
            'sort' => 0,
        ];
    }
}
