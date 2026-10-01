<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Стартовые SEO-тексты разделов каталога (ТЗ §5.5, §14) из database/seeders/category-texts.php.
 * Заполняются только пустые поля: текст, который написал администратор, остаётся. Раздел,
 * которого в базе нет, пропускается и называется в ответе. Запуск на сервере:
 *
 *   dc exec app php artisan db:seed --class=CategoryTextsSeeder --force
 */
class CategoryTextsSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array<string, string> $texts */
        $texts = require __DIR__.'/category-texts.php';

        $filled = 0;
        $kept = 0;
        $missing = [];

        foreach ($texts as $slug => $text) {
            $category = Category::query()->where('slug', $slug)->first();

            if ($category === null) {
                $missing[] = $slug;

                continue;
            }

            if (trim((string) $category->seo_text) !== '') {
                $kept++;

                continue;
            }

            $category->forceFill(['seo_text' => trim($text)])->save();
            $filled++;
        }

        $this->command?->info("SEO-тексты разделов: внесено {$filled}, оставлено написанных администратором {$kept}.");

        if ($missing !== []) {
            $this->command?->warn('Разделов нет в базе: '.implode(', ', $missing));
        }
    }
}
