<?php

use App\Enums\PriceKind;
use App\Models\Page;
use App\Models\PriceTier;
use App\Models\Setting;
use App\Models\Supplier;
use App\Support\Percent;
use Database\Seeders\ProductionSeeder;

it('seeds reference data and keeps administrator changes on a re-run', function () {
    $this->seed(ProductionSeeder::class);

    PriceTier::query()->where('slug', 'opt-1')->firstOrFail()->update(['discount_percent' => Percent::fromDecimal('12')]);
    Setting::query()->where('key', 'catalog.low_stock_threshold')->firstOrFail()->update(['value' => 5]);

    $this->seed(ProductionSeeder::class);

    expect(PriceTier::query()->count())->toBe(4)
        ->and(PriceTier::query()->where('slug', 'opt-1')->firstOrFail()->discount_percent->toDecimal())->toBe('12.00')
        ->and(PriceTier::query()->where('is_default', true)->pluck('slug')->all())->toBe(['retail'])
        ->and(Setting::query()->count())->toBe(count(ProductionSeeder::SETTINGS))
        ->and(Setting::query()->where('key', 'catalog.low_stock_threshold')->firstOrFail()->value)->toBe(5)
        ->and(Page::query()->pluck('slug')->sort()->values()->all())->toBe(collect(array_keys(ProductionSeeder::PAGES))->sort()->values()->all());
});

it('fills the required pages with starting texts and keeps what the administrator wrote', function () {
    Page::factory()->create(['slug' => 'dostavka', 'title' => 'Доставка', 'content' => 'Текст администратора', 'is_active' => false]);

    $this->seed(ProductionSeeder::class);

    $pages = Page::query()->get()->keyBy('slug');

    expect($pages->every(fn (Page $page): bool => ProductionSeeder::pageText($page->slug) !== null))->toBeTrue()
        ->and($pages['dostavka']->content)->toBe('Текст администратора')
        ->and($pages['dostavka']->is_active)->toBeFalse()
        ->and($pages['oplata']->content)->toBe(ProductionSeeder::pageText('oplata'))
        ->and($pages->where('slug', '!=', 'dostavka')->every(fn (Page $page): bool => $page->is_active))->toBeTrue();

    $this->get($pages['garantiya']->url())->assertOk()->assertSee('гаранти');
});

it('starts with the safe pricing and privacy defaults', function () {
    $this->seed(ProductionSeeder::class);

    $settings = Setting::query()->pluck('value', 'key');

    expect(Setting::query()->where('key', 'pricing.max_discount_without_purchase')->firstOrFail()->value)->toBe(0)
        ->and(Setting::query()->where('key', 'pricing.show_tier_name')->firstOrFail()->value)->toBeFalse()
        ->and(Setting::query()->where('key', 'notify.telegram_include_contacts')->firstOrFail()->value)->toBeFalse()
        ->and(Setting::query()->where('key', 'payments.online_enabled')->firstOrFail()->value)->toBeFalse()
        ->and($settings)->toHaveKey('seller.vat_mode');
});

it('creates the supplier with both import profiles switched off', function () {
    $this->seed(ProductionSeeder::class);

    $supplier = Supplier::query()->where('slug', ProductionSeeder::ROSHOLOD_SLUG)->firstOrFail();

    expect($supplier->price_kind)->toBe(PriceKind::Rrp)
        ->and($supplier->markup_percent->isZero())->toBeTrue()
        ->and($supplier->importProfiles()->pluck('source')->sort()->values()->all())->toBe(['rosholod.catalog_xml', 'rosholod.stock_xml'])
        ->and($supplier->importProfiles()->where('is_active', true)->exists())->toBeFalse();
});
