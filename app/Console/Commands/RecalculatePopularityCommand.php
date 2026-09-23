<?php

namespace App\Console\Commands;

use App\Actions\Catalog\RecalculatePopularity;
use Illuminate\Console\Command;

final class RecalculatePopularityCommand extends Command
{
    protected $signature = 'popularity:recalculate';

    protected $description = 'Пересчитать популярность товаров по заявкам и лидам за 90 дней (ТЗ §8.2)';

    public function handle(RecalculatePopularity $popularity): int
    {
        $this->info(__('shop.seo.popularity_done', ['count' => $popularity->handle()]));

        return self::SUCCESS;
    }
}
