<?php

use App\Actions\Seo\GenerateSitemap;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->articlesPath = storage_path('framework/testing/articles-'.bin2hex(random_bytes(8)));
    File::makeDirectory($this->articlesPath, recursive: true);
    config([
        'seo.articles_directory' => $this->articlesPath,
        'seo.articles_index' => $this->articlesPath.'/published.json',
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->articlesPath);
    File::delete(GenerateSitemap::path());
});

function seoArticle(string $slug): string
{
    return <<<MD
---
site: gastrosnab.ru
slug: {$slug}
title: "Как выбрать оборудование | Гастроснаб"
description: "Подбор оборудования для кухни."
h1: "Руководство {$slug}"
date: 2026-11-07
category: "Тепловое оборудование"
main_query: "оборудование для кухни"
queries: ["оборудование для кафе", "оборудование для столовой"]
links: ["/catalog/teplovoe", "/catalog"]
cta: "Пришлите план кухни"
read_time: 9
---

# Подбор по параметрам

[[ФОТО: пароконвектомат | Фото оборудования]]

[Другая статья](article:sosednyaya) и [Черновик](article:chernovik).

| Параметр | Значение |
| --- | --- |
| Мощность | См. паспорт |

## Частые вопросы

### Можно подключить к обычной розетке

Сверьте требования в паспорте.

## Что дальше

Выберите оборудование.
MD;
}

it('publishes articles, catalog links, related pages and sitemap entries', function () {
    $category = Category::factory()->create(['name' => 'Тепловое оборудование', 'slug' => 'teplovoe', 'is_active' => true]);
    $product = Product::factory()->create(['category_id' => $category->id]);
    File::put($this->articlesPath.'/vybor.md', seoArticle('vybor'));
    File::put($this->articlesPath.'/sosednyaya.md', seoArticle('sosednyaya'));
    File::put($this->articlesPath.'/_index.md', 'Заметки редактора');
    $base = url('/articles').'/';

    $this->artisan('articles:import', ['--dry-run' => true])->assertSuccessful();
    $this->get($base.'vybor/?utm_source=test')->assertNotFound();
    $this->artisan('articles:import')->assertSuccessful();

    $this->get('/articles?utm_source=abc')->assertStatus(301)->assertRedirect(url('/articles').'/?utm_source=abc');
    $this->get($base.'?utm_source=test')->assertOk()->assertSee('Руководство vybor');
    $this->get($base.'missing/?utm_source=test')->assertNotFound();
    $response = $this->get($base.'vybor/?utm_source=test')->assertOk()
        ->assertSee('<title>Как выбрать оборудование | Гастроснаб</title>', false)
        ->assertSee('content="article"', false)
        ->assertSee('href="'.url('/articles/vybor').'/"', false)
        ->assertSee('href="/articles/sosednyaya/"', false)
        ->assertSee('href="/catalog/teplovoe"', false)
        ->assertSee('"@type":"Article"', false)
        ->assertSee('"@type":"FAQPage"', false)
        ->assertSee('BreadcrumbList')
        ->assertSee('Пришлите план кухни')
        ->assertSee('<table>', false)
        ->assertDontSee('Фото оборудования')
        ->assertDontSee('article:chernovik', false)
        ->assertDontSee('Выберите оборудование."}', false);

    expect(substr_count($response->getContent(), '<h1'))->toBe(1);
    expect(File::get(GenerateSitemap::path()))
        ->toContain('/articles/vybor/</loc><lastmod>2026-11-07</lastmod>');

    $this->get(route('category', $category))->assertOk()->assertSee('Руководство vybor');
    $this->get(route('product', $product))->assertOk()->assertSee('Руководство vybor');
});

it('keeps the published snapshot on a batch error and paginates the list', function () {
    foreach (range(1, 13) as $number) {
        $slug = 'statya-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT);
        File::put($this->articlesPath.'/'.$slug.'.md', seoArticle($slug));
    }
    $this->artisan('articles:import')->assertSuccessful();
    $base = url('/articles').'/';
    $this->get($base.'?page=1')->assertOk()->assertSee('href="'.$base.'?page=2"', false);
    $this->get($base.'?page=2')->assertOk()
        ->assertSee('Руководство statya-13')->assertDontSee('Руководство statya-01');
    $this->get($base.'?page=3')->assertNotFound();

    $snapshot = File::get(config('seo.articles_index'));
    File::put($this->articlesPath.'/broken.md', 'Нет шапки');
    File::put($this->articlesPath.'/statya-01.md', str_replace('Сверьте', 'Проверьте', seoArticle('statya-01')));
    $this->artisan('articles:import')->assertFailed()->expectsOutputToContain('broken.md');
    expect(File::get(config('seo.articles_index')))->toBe($snapshot);

    File::delete($this->articlesPath.'/broken.md');
    $this->travelTo(now()->setDate(2026, 11, 8));
    $this->artisan('articles:import')->assertSuccessful();
    $this->get($base.'statya-01/?utm_source=test')->assertOk()->assertSee('"dateModified":"2026-11-08"', false);
    $this->get($base.'statya-02/?utm_source=test')->assertOk()->assertSee('"dateModified":"2026-11-07"', false);
});
