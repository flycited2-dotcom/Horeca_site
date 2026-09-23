<?php

use App\Enums\UserRole;
use App\Filament\Pages\ManageSettings;
use App\Models\Setting;
use App\Models\Warehouse;
use Database\Seeders\ProductionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ProductionSeeder::class);
    $this->actingAs(staffUser(UserRole::Admin));
});

function settingValue(string $key): mixed
{
    return Setting::query()->where('key', $key)->value('value');
}

it('lets only an administrator open the settings', function () {
    $this->get(ManageSettings::getUrl())
        ->assertOk()
        ->assertSee('Магазин и контакты')
        ->assertSee('Оптовые цены')
        ->assertSee('Номер счётчика Яндекс Метрики');

    $this->actingAs(staffUser(UserRole::Manager))->get(ManageSettings::getUrl())->assertForbidden();
});

it('shows the stored values and saves them with their types', function () {
    Warehouse::factory()->create(['name' => 'Симферополь']);
    setting('contacts.phones', ['+7 978 000-11-22', '+7 800 200-31-30']);

    Livewire::test(ManageSettings::class)
        ->assertSchemaStateSet([
            'contacts__phones' => "+7 978 000-11-22\n+7 800 200-31-30",
            'catalog__discontinued_after_runs' => 3,
            'pricing__max_discount_without_purchase' => 0,
        ], 'form')
        ->fillForm([
            'site__name' => ' Гастроснаб ',
            'contacts__phones' => "+7 978 000-11-22\r\n+7 800 200-31-30",
            'contacts__email' => 'shop@gastrosnab.ru',
            'contacts__schedule' => 'Пн–Пт 9:00–18:00',
            'contacts__address' => '',
            'seller__vat_mode' => 'without_vat',
            'delivery__free_city_from' => '15000',
            'catalog__local_warehouse_name' => 'Симферополь',
            'catalog__discontinued_after_runs' => '4',
            'pricing__max_discount_without_purchase' => '12.50',
            'pricing__min_margin_percent' => '10',
            'pricing__show_tier_name' => true,
            'analytics__metrika_id' => '98765432',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Настройки сохранены');

    expect(settingValue('site.name'))->toBe('Гастроснаб')
        ->and(settingValue('contacts.phones'))->toBe("+7 978 000-11-22\n+7 800 200-31-30")
        ->and(settingValue('contacts.address'))->toBeNull()
        ->and(settingValue('seller.vat_mode'))->toBe('without_vat')
        ->and(settingValue('delivery.free_city_from'))->toBe(15000)
        ->and(settingValue('catalog.discontinued_after_runs'))->toBe(4)
        ->and(settingValue('pricing.max_discount_without_purchase'))->toBe('12.5')
        ->and(settingValue('pricing.min_margin_percent'))->toBe('10')
        ->and(settingValue('pricing.show_tier_name'))->toBeTrue()
        ->and(settingValue('notify.telegram_include_contacts'))->toBeFalse()
        ->and(settingValue('analytics.metrika_id'))->toBe('98765432');
});

it('refuses values the storefront could not use', function () {
    Livewire::test(ManageSettings::class)
        ->fillForm([
            'contacts__email' => 'не почта',
            'analytics__metrika_id' => 'ym-123',
            'pricing__max_discount_without_purchase' => '95',
            'catalog__discontinued_after_runs' => '0',
        ])
        ->call('save')
        ->assertHasFormErrors([
            'contacts__email',
            'analytics__metrika_id',
            'pricing__max_discount_without_purchase',
            'catalog__discontinued_after_runs',
        ]);

    expect(settingValue('pricing.max_discount_without_purchase'))->toBe(0);
});

it('puts the saved contacts on the storefront at once', function () {
    Livewire::test(ManageSettings::class)
        ->fillForm(['contacts__phones' => '+7 978 555-66-77', 'site__name' => 'Гастроснаб'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('+7 978 555-66-77')
        ->assertSee('href="tel:+79785556677"', false);
});
