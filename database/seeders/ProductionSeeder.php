<?php

namespace Database\Seeders;

use App\Enums\PriceKind;
use App\Models\ImportProfile;
use App\Models\Page;
use App\Models\PriceTier;
use App\Models\Setting;
use App\Models\Supplier;
use App\Support\Money;
use App\Support\Percent;
use Illuminate\Database\Seeder;

/**
 * Reference data required on every environment (TZ §16).
 *
 * Safe to run repeatedly: existing records are never overwritten,
 * so values changed by the administrator survive a re-run.
 */
class ProductionSeeder extends Seeder
{
    public const string ROSHOLOD_SLUG = 'rosholod';

    /**
     * Price tiers: slug => [name, discount in basis points, is default, sort].
     *
     * Starting discounts from TZ §20 are to be confirmed by the customer.
     */
    private const array PRICE_TIERS = [
        'retail' => ['Розница', 0, true, 0],
        'opt-1' => ['Опт-1', 1000, false, 10],
        'opt-2' => ['Опт-2', 1500, false, 20],
        'chain' => ['Сеть', 2000, false, 30],
    ];

    /**
     * Setting keys with defaults from TZ §5.5; null means "to be provided by the customer".
     */
    public const array SETTINGS = [
        'site.name' => null,
        'contacts.phones' => null,
        'contacts.email' => null,
        'contacts.address' => null,
        'contacts.schedule' => null,
        'contacts.socials' => null,
        'seller.requisites' => null,
        'seller.vat_mode' => null,
        'pickup.address' => null,
        'delivery.free_city_from' => null,
        'catalog.low_stock_threshold' => 3,
        'catalog.discontinued_after_runs' => 3,
        'catalog.local_warehouse_name' => 'Симферополь',
        'catalog.local_strip_min_products' => 12,
        'pricing.max_discount_without_purchase' => 0,
        'pricing.min_margin_percent' => null,
        'pricing.show_tier_name' => false,
        'notify.telegram_include_contacts' => false,
        'payments.online_enabled' => false,
        'analytics.metrika_id' => null,
        'seo.product_title_template' => '{name} — купить в Симферополе, цена {price} | {site}',
        'seo.category_title_template' => '{name} — купить в Симферополе | {site}',
    ];

    /**
     * Required pages (TZ §5.5): slug => title. Created inactive until the customer provides texts.
     */
    public const array PAGES = [
        'dostavka' => 'Доставка',
        'oplata' => 'Оплата',
        'garantiya' => 'Гарантия',
        'optovikam' => 'Оптовикам',
        'o-kompanii' => 'О компании',
        'kontakty' => 'Контакты',
        'politika-konfidencialnosti' => 'Политика конфиденциальности',
        'soglasie-na-obrabotku-personalnyh-dannyh' => 'Согласие на обработку персональных данных',
        'polzovatelskoe-soglashenie' => 'Пользовательское соглашение',
    ];

    public function run(): void
    {
        $this->seedPriceTiers();
        $this->seedSettings();
        $this->seedPages();
        $this->seedSupplier();
    }

    private function seedPriceTiers(): void
    {
        foreach (self::PRICE_TIERS as $slug => [$name, $discount, $isDefault, $sort]) {
            PriceTier::query()->firstOrCreate(['slug' => $slug], [
                'name' => $name,
                'discount_percent' => Percent::ofBasisPoints($discount),
                'min_order_amount' => Money::zero(),
                'is_default' => $isDefault,
                'sort' => $sort,
            ]);
        }
    }

    private function seedSettings(): void
    {
        foreach (self::SETTINGS as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    private function seedPages(): void
    {
        $sort = 0;

        foreach (self::PAGES as $slug => $title) {
            Page::query()->firstOrCreate(['slug' => $slug], [
                'title' => $title,
                'content' => '',
                'is_active' => false,
                'sort' => $sort += 10,
            ]);
        }
    }

    private function seedSupplier(): void
    {
        $supplier = Supplier::query()->firstOrCreate(['slug' => self::ROSHOLOD_SLUG], [
            'name' => 'Росхолод',
            'price_kind' => PriceKind::Rrp,
            'markup_percent' => Percent::zero(),
            'retail_round_to' => 1,
            'is_active' => true,
        ]);

        $thresholds = [
            'invalid_rows_max_percent' => 30,
            'min_records_percent_of_previous' => 80,
        ];

        $profiles = [
            'rosholod.catalog_xml' => ['Росхолод: каталог (XML)', 'https://rosholod.org/price-lists/Catalog.xml', '0 5-23 * * *'],
            'rosholod.stock_xml' => ['Росхолод: остатки (XML)', 'https://rosholod.org/price-lists/OstatkiYandex.xml', '*/30 6-23 * * *'],
        ];

        foreach ($profiles as $source => [$name, $url, $schedule]) {
            ImportProfile::query()->firstOrCreate(['supplier_id' => $supplier->id, 'source' => $source], [
                'name' => $name,
                'url' => $url,
                'schedule' => $schedule,
                'settings' => $thresholds,
                'is_active' => false,
            ]);
        }
    }
}
