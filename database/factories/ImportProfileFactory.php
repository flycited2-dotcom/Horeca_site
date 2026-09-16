<?php

namespace Database\Factories;

use App\Models\ImportProfile;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportProfile>
 */
class ImportProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'name' => 'Профиль импорта '.fake()->unique()->numberBetween(1, 999_999),
            'source' => 'rosholod.catalog_xml',
            'url' => 'https://supplier.example/catalog.xml',
            'schedule' => null,
            'settings' => [
                'invalid_rows_max_percent' => 30,
                'min_records_percent_of_previous' => 80,
            ],
            'is_active' => false,
        ];
    }
}
