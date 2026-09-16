<?php

namespace Database\Factories;

use App\Enums\ImportEntity;
use App\Models\ImportRow;
use App\Models\ImportRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportRow>
 */
class ImportRowFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $payload = ['name' => 'Холодильный шкаф ШХ-'.fake()->numberBetween(100, 999)];

        return [
            'import_run_id' => ImportRun::factory(),
            'entity' => ImportEntity::Product,
            'external_id' => fake()->uuid(),
            'payload' => $payload,
            'hash' => md5((string) json_encode($payload)),
            'is_valid' => true,
            'error' => null,
        ];
    }
}
