<?php

namespace App\Console\Commands;

use App\Actions\Seo\GenerateSitemap;
use App\Services\Seo\ArticleLibrary;
use App\Services\Seo\ArticleSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

final class ImportArticlesCommand extends Command
{
    protected $signature = 'articles:import {--dry-run : Проверить файлы без публикации}';

    protected $description = 'Проверить и опубликовать статьи Gastrosnab из папки Markdown';

    public function handle(ArticleLibrary $library, GenerateSitemap $sitemap): int
    {
        $directory = config('seo.articles_directory');
        if (! File::isDirectory($directory)) {
            $this->error('Папка статей не найдена: '.$directory);

            return self::FAILURE;
        }

        $lock = Cache::lock('articles:import', 300);
        if (! $lock->get()) {
            $this->error('Импорт статей уже выполняется.');

            return self::FAILURE;
        }

        try {
            $articles = $library->all()->all();
            $count = 0;
            $hasErrors = false;
            foreach (File::files($directory) as $file) {
                if ($file->getExtension() !== 'md' || str_starts_with($file->getFilename(), '_')) {
                    continue;
                }

                try {
                    $article = ArticleSource::parse(File::get($file->getPathname()), $file->getFilename());
                    $slug = $article['slug'];
                    $previous = $articles[$slug] ?? [];
                    $oldDate = $previous['modified'] ?? $article['date'];
                    unset($previous['modified']);
                    $article['modified'] = $previous === [] || $previous === $article ? $oldDate : max($article['date'], today()->toDateString());
                    $articles[$slug] = $article;
                    $count++;
                } catch (InvalidArgumentException $exception) {
                    $this->error($file->getFilename().': '.$exception->getMessage());
                    $hasErrors = true;
                }
            }

            if ($hasErrors || $count === 0) {
                $this->error('Импорт отменён. Нужен хотя бы один корректный файл статьи.');

                return self::FAILURE;
            }

            if (! $this->option('dry-run')) {
                $articles = collect($articles)->sortBy([['date', 'desc'], ['slug', 'asc']]);
                File::replace(config('seo.articles_index'), $articles->toJson(JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 0644);
                $sitemap->handle();
            }

            $this->info(($this->option('dry-run') ? 'Проверено файлов: ' : 'Импортировано файлов: ').$count);

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
