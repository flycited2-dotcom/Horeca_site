<?php

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 999_999);

        return [
            'slug' => "page-{$number}",
            'title' => "Страница {$number}",
            'content' => fake()->realText(400),
            'meta_title' => null,
            'meta_description' => null,
            'is_active' => true,
            'sort' => 0,
        ];
    }
}
