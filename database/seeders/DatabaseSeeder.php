<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Model events stay enabled: products derive availability_rank on save.
     */
    public function run(): void
    {
        $this->call(ProductionSeeder::class);

        if (app()->isLocal()) {
            $this->call(DemoSeeder::class);
        }
    }
}
