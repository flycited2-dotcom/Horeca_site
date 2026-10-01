<?php

use App\Enums\Availability;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    setting('site.name', 'Гастроснаб');
    $this->abat = Brand::factory()->create(['name' => 'Abat', 'slug' => 'abat']);
    $this->category = Category::factory()->create(['name' => 'Пароконвектоматы', 'slug' => 'parokonvektomaty', 'is_active' => true, 'products_count' => 21]);
});

function titleOf(string $html): string
{
    preg_match('/<title>(.*?)<\/title>/su', $html, $match);

    return html_entity_decode($match[1] ?? '', ENT_QUOTES);
}

it('builds a product title and description from the templates', function () {
    $product = Product::factory()->create([
        'name' => 'Пароконвектомат ПКА 10-1/1',
        'slug' => 'pka-10',
        'sku' => '11000019106',
        'category_id' => $this->category->id,
        'brand_id' => $this->abat->id,
        'retail_price' => Money::ofRubles(383_995),
        'availability' => Availability::InStock,
    ]);

    $html = $this->get(route('product', $product))->assertOk()->getContent();

    expect(titleOf($html))->toBe("Пароконвектомат ПКА 10-1/1 — купить в Симферополе, цена 383\u{00A0}995\u{00A0}₽ | Гастроснаб")
        ->and($html)->toContain('<meta name="description" content="Пароконвектомат ПКА 10-1/1 — цена 383'."\u{00A0}".'995'."\u{00A0}".'₽, в наличии. Бренд Abat. Артикул 11000019106.">')
        ->and($html)->toContain('<link rel="canonical" href="'.route('product', $product).'">')
        ->and($html)->not->toContain('name="robots"');
});

it('drops the price from the title when it is on request and keeps what the manager wrote', function () {
    $onRequest = Product::factory()->create(['name' => 'Печь КЭП-10', 'category_id' => $this->category->id, 'retail_price' => null]);
    $manual = Product::factory()->create(['name' => 'Плита', 'category_id' => $this->category->id, 'meta_title' => 'Плита для столовой', 'meta_description' => 'Своё описание.']);

    expect(titleOf($this->get(route('product', $onRequest))->getContent()))->toBe('Печь КЭП-10 — купить в Симферополе | Гастроснаб');

    $this->get(route('product', $manual))
        ->assertSee('<title>Плита для столовой</title>', false)
        ->assertSee('<meta name="description" content="Своё описание.">', false);
});

it('follows the templates from the settings', function () {
    setting('seo.category_title_template', '{name} в Крыму — {site}');
    setting('seo.product_title_template', '{name}: {price} — {site}');
    $product = Product::factory()->create(['name' => 'Слайсер', 'category_id' => $this->category->id, 'retail_price' => Money::ofRubles(50_000)]);

    expect(titleOf($this->get(route('category', $this->category))->getContent()))->toBe('Пароконвектоматы в Крыму — Гастроснаб')
        ->and(titleOf($this->get(route('product', $product))->getContent()))->toBe("Слайсер: 50\u{00A0}000\u{00A0}₽ — Гастроснаб");
});

it('closes listings with several filters from the index and names the canonical address', function () {
    $base = route('category', $this->category);

    $plain = $this->get($base)->assertOk()->getContent();
    expect(titleOf($plain))->toBe('Пароконвектоматы — купить в Симферополе | Гастроснаб')
        ->and($plain)->toContain('21 модель в каталоге Гастроснаб')
        ->and($plain)->toContain('<link rel="canonical" href="'.$base.'">')
        ->and($plain)->not->toContain('name="robots"');

    $oneFilter = $this->get($base.'?brand[]=abat&view=list&utm_source=ya')->getContent();
    expect($oneFilter)->not->toContain('name="robots"')
        ->and($oneFilter)->toContain('<link rel="canonical" href="'.$base.'?'.e(http_build_query(['brand' => ['abat']])).'">');

    expect($this->get($base.'?brand[]=abat&in_stock=1')->getContent())->toContain('<meta name="robots" content="noindex, follow">')
        ->and($this->get($base.'?brand[]=abat&sort=price_asc')->getContent())->toContain('<meta name="robots" content="noindex, follow">')
        ->and($this->get($base.'?page=2')->getContent())->toContain('<link rel="canonical" href="'.$base.'?page=2">');
});

it('describes a brand and a page', function () {
    $page = Page::factory()->create(['slug' => 'dostavka', 'title' => 'Доставка', 'is_active' => true, 'content' => "## По городу\n\nВ день заказа, **бесплатно** от 15 000 ₽."]);

    expect(titleOf($this->get(route('brand', $this->abat))->getContent()))->toBe('Каталог Abat | Гастроснаб');

    $html = $this->get('/dostavka')->assertOk()->getContent();

    expect(titleOf($html))->toBe('Доставка | Гастроснаб')
        ->and($html)->toContain('<meta name="description" content="По городу В день заказа, бесплатно от 15 000 ₽.">')
        ->and($html)->toContain('<link rel="canonical" href="'.url('dostavka').'">');
});

it('gives every page an icon and a preview of the link, with the photo of the product on its card', function () {
    Storage::fake('public');
    config(['media-library.disk_name' => 'public']);

    $home = $this->get('/')->assertOk()->getContent();

    expect($home)->toContain('<link rel="icon" href="'.asset('favicon.ico').'" sizes="any">', '<link rel="icon" href="'.asset('favicon.svg').'" type="image/svg+xml">', '<link rel="apple-touch-icon" href="'.asset('apple-touch-icon.png').'">')
        ->and($home)->toContain('<meta property="og:site_name" content="Гастроснаб">', '<meta property="og:image" content="'.asset('og-image.png').'">', '<meta name="twitter:card" content="summary_large_image">');

    $product = Product::factory()->create(['name' => 'Шкаф с фото', 'slug' => 'shkaf-s-foto-og', 'category_id' => $this->category->id]);
    $product->addMedia(UploadedFile::fake()->image('shkaf.jpg', 800, 600))->toMediaCollection(Product::IMAGES);

    $card = $this->get(route('product', $product))->assertOk()->getContent();

    expect($card)->toContain('<meta property="og:image" content="'.$product->getFirstMediaUrl(Product::IMAGES, 'full').'">')
        ->and($card)->toContain('<meta property="og:url" content="'.route('product', $product).'">')
        ->and($card)->not->toContain('content="'.asset('og-image.png').'"');
});

it('has the files that the page head points to', function () {
    foreach (['favicon.ico', 'favicon.svg', 'apple-touch-icon.png', 'og-image.png'] as $file) {
        expect(filesize(public_path($file)))->toBeGreaterThan(300);
    }

    [$width, $height] = getimagesize(public_path('og-image.png'));

    expect([$width, $height])->toBe([1200, 630]);
});
