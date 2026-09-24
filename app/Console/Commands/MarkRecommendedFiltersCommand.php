<?php

namespace App\Console\Commands;

use App\Actions\Catalog\MarkRecommendedFilters;
use Illuminate\Console\Command;

final class MarkRecommendedFiltersCommand extends Command
{
    protected $signature = 'catalog:recommended-filters';

    protected $description = 'Сделать фильтрами каталога рекомендованные характеристики — мощность, объём, напряжение и другие (ТЗ §8.2)';

    public function handle(MarkRecommendedFilters $filters): int
    {
        $result = $filters->handle();

        $this->info(__('shop.catalog.recommended_filters_done', ['count' => $result['marked']]));

        if ($result['missing'] !== []) {
            $this->warn(__('shop.catalog.recommended_filters_missing', ['slugs' => implode(', ', $result['missing'])]));
        }

        return self::SUCCESS;
    }
}
