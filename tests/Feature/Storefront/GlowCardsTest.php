<?php

use App\Enums\Availability;
use App\Livewire\CategoryListing;
use App\Livewire\SearchListing;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    config(['media-library.disk_name' => 'public', 'media-library.queue_conversions_by_default' => false]);
});

/**
 * Карточки и элементы витрины в облике «Свечение» (ТЗ §9): свечение карточки по зоне раздела,
 * плашка под фото, метка наличия словом и формой маркера, круглая кнопка корзины, плитки разделов,
 * строки и таблица без свечения, панель покупки. Стили лежат в resources/css/glow-cards.css, здесь — разметка.
 */
function glowArticles(string $html): array
{
    preg_match_all('/<article\b.*?<\/article>/s', $html, $matches);

    return $matches[0];
}

function glowArticleOf(string $html, string $needle): string
{
    foreach (glowArticles($html) as $article) {
        if (str_contains($article, $needle)) {
            return $article;
        }
    }

    return '';
}

function glowClassesOf(string $article): string
{
    preg_match('/<article\b[^>]*class="([^"]*)"/', $article, $match);

    return $match[1] ?? '';
}

it('rings a listing card with the glow of the zone of its section', function (?string $icon, string $name, string $zone) {
    $category = Category::factory()->create(['name' => $name, 'slug' => 'razdel', 'icon' => $icon]);
    Product::factory()->inStock()->create(['name' => 'Образец товара', 'category_id' => $category->id]);

    $html = $this->get('/catalog/razdel')->assertOk()->getContent();
    $classes = glowClassesOf(glowArticleOf($html, 'Образец товара'));

    expect($classes)->toContain('gl-card', 'gl-pcard', $zone);

    foreach (['gl-cold', 'gl-hot', 'gl-neutral'] as $other) {
        if ($other !== $zone) {
            expect($classes)->not->toContain($other);
        }
    }
})->with([
    'cold by the icon' => ['refrigeration', 'Раздел А', 'gl-cold'],
    'hot by the icon' => ['thermal', 'Раздел Б', 'gl-hot'],
    'neutral by the icon' => ['neutral', 'Раздел В', 'gl-neutral'],
    'cold by the name when the icon is empty' => [null, 'Морозильные лари', 'gl-cold'],
    'neutral without any hint' => [null, 'Разное', 'gl-neutral'],
]);

it('puts the status on the photo plate in words and in the form of its marker', function () {
    $category = Category::factory()->create(['name' => 'Шкафы', 'slug' => 'shkafy', 'icon' => 'refrigeration']);
    Product::factory()->inStock()->create(['name' => 'Шкаф в наличии', 'category_id' => $category->id]);
    Product::factory()->create(['name' => 'Шкаф под заказ', 'category_id' => $category->id, 'availability' => Availability::OnOrder]);

    $html = $this->get('/catalog/shkafy')->assertOk()->getContent();
    $inStock = glowArticleOf($html, 'Шкаф в наличии');
    $onOrder = glowArticleOf($html, 'Шкаф под заказ');

    expect($inStock)->toContain('gl-pcard__stock', 'gl-av gl-av--in', 'gl-av__m', 'В наличии')
        ->not->toContain('gl-av--order')
        ->and($onOrder)->toContain('gl-pcard__stock', 'gl-av--order', 'Под заказ')
        ->not->toContain('gl-av--in');
});

it('gives a priced card a round cart button named for the product and an unpriced one the request button', function () {
    $category = Category::factory()->create(['name' => 'Шкафы', 'slug' => 'shkafy']);
    Product::factory()->inStock()->create(['name' => 'Шкаф с ценой', 'category_id' => $category->id, 'retail_price' => Money::ofRubles(50_000)]);
    Product::factory()->create(['name' => 'Шкаф без цены', 'category_id' => $category->id, 'retail_price' => null]);

    $html = $this->get('/catalog/shkafy')->assertOk()->getContent();
    $priced = glowArticleOf($html, 'Шкаф с ценой');
    $unpriced = glowArticleOf($html, 'Шкаф без цены');

    expect($priced)->toContain('data-cart-form', 'gl-round--cart', 'Добавить в корзину: Шкаф с ценой', "50\u{00A0}000\u{00A0}₽", 'gl-price')
        ->not->toContain('data-lead-product-id')
        ->and($unpriced)->toContain('Цена по запросу', 'Запросить цену', 'data-lead-product-id')
        ->not->toContain('data-cart-form')
        ->not->toContain('gl-round--cart');
});

it('makes the whole card one link to the product and keeps compare and favourites over it', function () {
    $category = Category::factory()->create(['name' => 'Шкафы', 'slug' => 'shkafy']);
    $product = Product::factory()->inStock()->create(['name' => 'Шкаф ссылкой', 'category_id' => $category->id]);

    $article = glowArticleOf($this->get('/catalog/shkafy')->assertOk()->getContent(), 'Шкаф ссылкой');

    // Фото ведёт туда же, но для клавиатуры и чтеца пропускается: заголовок — единственная доступная ссылка.
    expect($article)->toContain('href="'.route('product', $product).'"', 'tabindex="-1"', 'gl-pcard__name', 'gl-pcard__tools')
        ->and($article)->toContain('data-compare="'.$product->id.'"', 'data-favorite="'.$product->id.'"')
        ->and($article)->toContain('<h3');
});

it('lays the photo of a product on a white plate and shows the type of equipment when there is none', function () {
    $category = Category::factory()->create(['name' => 'Печи', 'slug' => 'pechi', 'icon' => 'thermal']);
    $withPhoto = Product::factory()->inStock()->create(['name' => 'Печь с фото', 'category_id' => $category->id]);
    $withPhoto->addMedia(UploadedFile::fake()->image('pech.jpg', 800, 600))->toMediaCollection(Product::IMAGES);
    Product::factory()->inStock()->create(['name' => 'Печь без фото', 'category_id' => $category->id]);

    $html = $this->get('/catalog/pechi')->assertOk()->getContent();
    $photo = glowArticleOf($html, 'Печь с фото');
    $none = glowArticleOf($html, 'Печь без фото');

    expect($photo)->toContain('gl-plate gl-pimg', 'gl-pimg--photo', '<img', $withPhoto->refresh()->getFirstMediaUrl(Product::IMAGES, 'card'))
        ->and($none)->toContain('gl-plate gl-pimg', 'Фото нет, показан тип оборудования', 'Тепловое')
        ->not->toContain('gl-pimg--photo')
        ->not->toContain('<img');
});

it('shows the same card for the products related to the product page', function () {
    $category = Category::factory()->create(['name' => 'Шкафы', 'slug' => 'shkafy', 'icon' => 'refrigeration']);
    $brand = Brand::factory()->create(['name' => 'Abat', 'slug' => 'abat']);
    $main = Product::factory()->inStock()->create(['name' => 'Шкаф главный', 'category_id' => $category->id, 'brand_id' => $brand->id, 'retail_price' => Money::ofRubles(50_000)]);
    Product::factory()->inStock()->create(['name' => 'Шкаф похожий', 'category_id' => $category->id, 'brand_id' => $brand->id, 'retail_price' => Money::ofRubles(52_000)]);

    $article = glowArticleOf($this->get(route('product', $main))->assertOk()->getContent(), 'Шкаф похожий');

    expect(glowClassesOf($article))->toContain('gl-card', 'gl-pcard', 'gl-cold');
});

it('keeps the list view as a table without the glow of cards', function () {
    $category = Category::factory()->create(['name' => 'Шкафы', 'slug' => 'shkafy', 'icon' => 'refrigeration']);
    Product::factory()->inStock()->create(['name' => 'Шкаф для таблицы', 'category_id' => $category->id, 'retail_price' => Money::ofRubles(70_000)]);

    Livewire::test(CategoryListing::class, ['category' => $category, 'title' => $category->name])
        ->call('setView', 'list')
        ->assertSeeHtml('gl-tablewrap')
        ->assertSeeHtml('<table')
        ->assertSeeHtml('gl-price gl-price--row')
        ->assertSeeHtml('gl-btn--calm')
        ->assertSeeHtml('gl-counter');
});

it('shows search results as plain rows and the exact article as a warm glass card', function () {
    $ovens = Category::factory()->create(['name' => 'Пароконвектоматы', 'slug' => 'parokonvektomaty', 'icon' => 'thermal']);
    Product::factory()->inStock()->create(['name' => 'Пароконвектомат ПКА 10-1/1ВП2-01', 'sku' => '11000019106', 'model' => 'ПКА 10-1/1ВП2-01', 'category_id' => $ovens->id, 'retail_price' => Money::ofRubles(383_995)]);
    Product::factory()->create(['name' => 'Подставка под 11000019106', 'sku' => '11000044170', 'category_id' => $ovens->id, 'retail_price' => Money::ofRubles(1_500)]);

    $html = Livewire::test(SearchListing::class, ['query' => '11000019106'])->html();
    $exact = glowArticleOf($html, 'Пароконвектомат ПКА 10-1/1ВП2-01');
    $row = glowArticleOf($html, 'Подставка под 11000019106');

    expect(glowClassesOf($exact))->toContain('gl-prow', 'gl-card', 'gl-hot')
        ->and($exact)->toContain('gl-badge', 'Точное совпадение по артикулу', 'Открыть карточку')
        ->and(glowClassesOf($row))->toContain('gl-prow')->not->toContain('gl-card')
        ->and($row)->toContain('data-cart-form');
});

it('turns the subsections into glass tiles and round chips with the glow of their zone', function () {
    $root = Category::factory()->create(['name' => 'Оборудование', 'slug' => 'oborudovanie']);
    $cold = Category::factory()->childOf($root)->create(['name' => 'Шкафы холодильные', 'slug' => 'shkafy', 'icon' => 'refrigeration', 'products_count' => 4]);
    Product::factory()->inStock()->create(['name' => 'Шкаф в разделе', 'category_id' => $cold->id]);

    $html = $this->get('/catalog/oborudovanie')->assertOk()->getContent();

    expect($html)->toContain('gl-lchip tap-target gl-cold', 'gl-lchip__n')
        ->and($html)->toContain('gl-card gl-ctile gl-cold gl-ctile--compact')
        ->and($html)->toContain('href="'.route('category', $cold).'"');
});

it('renders the tile of a section as a link with the zone glow and without the old flat look', function () {
    $html = (string) $this->blade(
        '<x-catalog.category-tile name="Холодильное оборудование" url="/catalog/holodilnoe" icon="refrigeration" compact><span>12 моделей</span></x-catalog.category-tile>'
    );

    expect($html)->toContain('<a', 'href="/catalog/holodilnoe"', 'gl-card gl-ctile', 'gl-cold', 'gl-ctile--compact', 'gl-plate gl-cpic', 'Холодильное оборудование', '12 моделей')
        ->not->toContain('data-zone')
        ->not->toContain('rounded-card');

    $hot = (string) $this->blade(
        '<x-catalog.category-tile name="Печи" url="/catalog/pechi" icon="thermal" zone="hot" featured><span>40 моделей</span></x-catalog.category-tile>'
    );

    expect($hot)->toContain('gl-hot', 'data-zone="hot"', 'data-featured', 'gl-ctile--featured', 'gl-badge', 'Жар');
});

it('keeps the original category picture for the pages that do not ask for a plate', function () {
    $html = (string) $this->blade('<x-catalog.category-picture image="/img/a.jpg" icon="thermal" ratio="aspect-[16/9]" class="border-b" />');

    expect($html)->toContain('bg-stage', 'aspect-[16/9]', '<img')->not->toContain('gl-plate');
});

it('writes the page numbers as round buttons and marks the current one', function () {
    $category = Category::factory()->create(['name' => 'Шкафы', 'slug' => 'shkafy']);
    Product::factory()->count(30)->create(['category_id' => $category->id]);

    $html = Livewire::test(CategoryListing::class, ['category' => $category, 'title' => $category->name])->html();

    expect($html)->toContain('gl-pg gl-pg--num gl-pg--on', 'aria-current="page"', 'gl-meter', 'rel="next"')
        ->and($html)->toContain('Показать ещё');
});

it('draws the sort and view switches as glass segments with the current one marked', function () {
    $category = Category::factory()->create(['name' => 'Шкафы', 'slug' => 'shkafy']);
    Product::factory()->create(['name' => 'Шкаф', 'category_id' => $category->id]);

    $html = Livewire::test(CategoryListing::class, ['category' => $category, 'title' => $category->name])->html();

    expect($html)->toContain('gl-seg', 'gl-seg gl-seg--icons', 'aria-current="true"')
        ->and($html)->toContain('gl-bar', 'gl-field', 'gl-fpanel');
});

it('colours the applied filter chips and the section chips', function () {
    $category = Category::factory()->create(['name' => 'Шкафы', 'slug' => 'shkafy']);
    $abat = Brand::factory()->create(['name' => 'Abat', 'slug' => 'abat']);
    Product::factory()->inStock()->create(['name' => 'Шкаф Abat', 'category_id' => $category->id, 'brand_id' => $abat->id]);

    Livewire::withQueryParams(['brand' => ['abat']])
        ->test(CategoryListing::class, ['category' => $category, 'title' => $category->name])
        ->assertSeeHtml('gl-fchip')
        ->assertSee('Сбросить всё');
});

it('wraps the buy panel in a glass card of the product zone and keeps the hooks of the script', function () {
    $category = Category::factory()->create(['name' => 'Печи', 'slug' => 'pechi', 'icon' => 'thermal']);
    $product = Product::factory()->inStock()->create(['name' => 'Печь панель', 'category_id' => $category->id, 'retail_price' => Money::ofRubles(120_000)]);

    $html = $this->get(route('product', $product))->assertOk()->getContent();
    preg_match('/<div data-buy-panel[^>]*class="([^"]*)"/', $html, $panel);

    expect($panel[1] ?? '')->toContain('gl-card', 'gl-pbuy', 'gl-hot')
        ->and($html)->toContain('data-sticky-buy', 'gl-sbar', 'data-tabs', 'Добавить в корзину');
});

it('shows the cart line with the photo on a plate', function () {
    $category = Category::factory()->create(['name' => 'Шкафы', 'slug' => 'shkafy', 'icon' => 'refrigeration']);
    $product = Product::factory()->inStock()->create(['name' => 'Шкаф в корзине', 'category_id' => $category->id, 'retail_price' => Money::ofRubles(2_500)]);

    putInCart($product, 2);

    $this->get(route('cart'))->assertOk()
        ->assertSee('Шкаф в корзине')
        ->assertSee('gl-pimg--thumb', false)
        ->assertSee('gl-cold', false)
        ->assertSee('gl-counter', false)
        ->assertSee("5\u{00A0}000\u{00A0}₽", false);
});
