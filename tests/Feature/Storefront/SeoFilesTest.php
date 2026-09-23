<?php

use App\Actions\Seo\GenerateSitemap;
use App\Enums\Availability;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;

afterEach(function () {
    @unlink(GenerateSitemap::path());
});

it('closes every site but the production one from search engines', function () {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertContent("User-agent: *\nDisallow: /\n");
});

it('opens the production site except the customer\'s own pages', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $robots = $this->get('/robots.txt')->assertOk()->getContent();

    expect($robots)->toContain("User-agent: *\nDisallow: /account\nDisallow: /cart\nDisallow: /checkout\nDisallow: /search\nDisallow: /favorites")
        ->and($robots)->toContain("User-agent: Yandex\nDisallow: /account")
        ->and($robots)->toContain('Clean-param: sort&view&utm_source&utm_medium&utm_campaign&utm_content&utm_term')
        ->and($robots)->toContain('Sitemap: '.route('sitemap'))
        ->and($robots)->not->toContain('/manage');
});

it('lists the storefront in the sitemap and nothing hidden', function () {
    $section = Category::factory()->create(['slug' => 'parokonvektomaty', 'is_active' => true]);
    $hiddenSection = Category::factory()->create(['slug' => 'skrytyy-razdel', 'is_active' => false]);
    $brand = Brand::factory()->create(['slug' => 'abat']);
    $listed = Product::factory()->create(['slug' => 'pka-10', 'category_id' => $section->id, 'brand_id' => $brand->id]);
    Product::factory()->create(['slug' => 'skrytyy-tovar', 'category_id' => $section->id, 'is_visible' => false]);
    Product::factory()->create(['slug' => 'snyatyy-tovar', 'category_id' => $section->id, 'availability' => Availability::Discontinued]);
    Product::factory()->create(['slug' => 'v-skrytom-razdele', 'category_id' => $hiddenSection->id]);
    Page::factory()->create(['slug' => 'dostavka', 'is_active' => true, 'content' => 'Текст']);
    Page::factory()->create(['slug' => 'optovikam', 'is_active' => true, 'content' => 'Текст']);
    Page::factory()->create(['slug' => 'garantiya', 'is_active' => false]);

    $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    $xml = file_get_contents($response->baseResponse->getFile()->getPathname());

    expect($xml)->toStartWith('<?xml version="1.0" encoding="UTF-8"?>')
        ->and($xml)->toContain('<loc>'.route('home').'</loc>')
        ->and($xml)->toContain('<loc>'.route('category', $section).'</loc>')
        ->and($xml)->toContain('<loc>'.route('product', $listed).'</loc><lastmod>'.$listed->updated_at->format('Y-m-d').'</lastmod>')
        ->and($xml)->toContain('<loc>'.route('brand', $brand).'</loc>')
        ->and($xml)->toContain('<loc>'.url('dostavka').'</loc>')
        ->and(substr_count($xml, '<loc>'.route('wholesale').'</loc>'))->toBe(1)
        ->and($xml)->not->toContain('skrytyy')
        ->and($xml)->not->toContain('snyatyy-tovar')
        ->and($xml)->not->toContain('v-skrytom-razdele')
        ->and($xml)->not->toContain('garantiya');

    expect(simplexml_load_string($xml))->not->toBeFalse();
});

it('rebuilds the sitemap by the nightly command', function () {
    $section = Category::factory()->create(['is_active' => true]);

    $this->artisan('sitemap:generate')->assertSuccessful()->expectsOutputToContain('Карта сайта собрана');

    Product::factory()->create(['slug' => 'novyy-tovar', 'category_id' => $section->id]);

    expect(file_get_contents(GenerateSitemap::path()))->not->toContain('novyy-tovar');

    $this->artisan('sitemap:generate')->assertSuccessful();

    expect(file_get_contents(GenerateSitemap::path()))->toContain('novyy-tovar');
});
