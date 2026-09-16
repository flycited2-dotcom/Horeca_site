<?php

namespace Database\Factories;

use App\Enums\ImportRunStatus;
use App\Enums\ImportTrigger;
use App\Models\ImportProfile;
use App\Models\ImportRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportRun>
 */
class ImportRunFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'import_profile_id' => ImportProfile::factory(),
            'user_id' => null,
            'trigger' => ImportTrigger::Manual,
            'status' => ImportRunStatus::Success,
            'is_dry_run' => false,
            'rows_total' => 0,
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'discontinued' => 0,
            'errors' => 0,
            'started_at' => now()->subMinutes(5),
            'finished_at' => now(),
        ];
    }
}
