<?php

use App\Models\Category;
use Database\Seeders\CategoryTextsSeeder;

/**
 * @return array<string, string>
 */
function categoryTexts(): array
{
    return require database_path('seeders/category-texts.php');
}

it('fills only the empty texts of existing sections and keeps what the administrator wrote', function () {
    $empty = Category::factory()->create(['slug' => 'stol-proizvodstvennyy', 'seo_text' => null]);
    $written = Category::factory()->create(['slug' => 'vanna-moechnaya', 'seo_text' => 'Текст администратора']);
    $blank = Category::factory()->create(['slug' => 'holodilnyy-shkaf', 'seo_text' => "  \n"]);
    $other = Category::factory()->create(['slug' => 'ne-v-spiske', 'seo_text' => null]);

    $this->seed(CategoryTextsSeeder::class);

    expect($empty->fresh()->seo_text)->toBe(trim(categoryTexts()['stol-proizvodstvennyy']))
        ->and($blank->fresh()->seo_text)->toBe(trim(categoryTexts()['holodilnyy-shkaf']))
        ->and($written->fresh()->seo_text)->toBe('Текст администратора')
        ->and($other->fresh()->seo_text)->toBeNull();

    $this->seed(CategoryTextsSeeder::class);

    expect($empty->fresh()->seo_text)->toBe(trim(categoryTexts()['stol-proizvodstvennyy']));
});

it('shows the text of the section under the list as headings and lists', function () {
    $category = Category::factory()->create(['slug' => 'stol-proizvodstvennyy', 'is_active' => true, 'seo_text' => null]);

    $this->seed(CategoryTextsSeeder::class);

    $this->get(route('category', $category))
        ->assertOk()
        ->assertSee('<h2>На что обратить внимание</h2>', false)
        ->assertSee('<li><strong>Борт.</strong>', false)
        ->assertDontSee('## На что обратить внимание', false);
});

it('writes every text by itself: a heading, enough words, no promises about price, stock or delivery', function () {
    $texts = collect(categoryTexts());

    expect($texts)->not->toBeEmpty()
        ->and($texts->keys()->reject(fn (string $slug): bool => preg_match('/^[a-z0-9-]+$/', $slug) === 1)->all())->toBe([], 'адреса разделов')
        ->and($texts->filter(fn (string $text): bool => mb_strlen($text) < 350)->keys()->all())->toBe([], 'слишком короткие')
        ->and($texts->reject(fn (string $text): bool => str_contains($text, "\n## "))->keys()->all())->toBe([], 'без подзаголовка')
        ->and($texts->filter(fn (string $text): bool => preg_match('/дешев|лучш(ие|ая|ий) цен|гарантируем|бесплатн|в наличии|со склада|скидк|акци[яи]|самые низкие/u', mb_strtolower($text)) === 1)->keys()->all())->toBe([], 'с обещанием')
        ->and($texts->map(fn (string $text): string => md5($text))->unique())->toHaveCount($texts->count());
});
