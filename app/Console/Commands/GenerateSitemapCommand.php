<?php

namespace App\Console\Commands;

use App\Actions\Seo\GenerateSitemap;
use Illuminate\Console\Command;

final class GenerateSitemapCommand extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Собрать карту сайта sitemap.xml (ТЗ §14)';

    public function handle(GenerateSitemap $sitemap): int
    {
        $this->info(__('shop.seo.sitemap_done', ['count' => $sitemap->handle()]));

        return self::SUCCESS;
    }
}
