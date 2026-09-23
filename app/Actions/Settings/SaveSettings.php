<?php

namespace App\Actions\Settings;

use App\Models\Setting;
use App\Services\Settings\Settings;
use Illuminate\Support\Facades\DB;

/**
 * Страница «Настройки» (ТЗ §12, §5.5): администратор задаёт контакты, реквизиты, режим НДС,
 * получение, каталог, цены, уведомления, аналитику и шаблоны SEO. Значения приводятся к типу
 * ключа; пустое поле — «ещё не задано» (null), как в §5.5. Сохранение — одной транзакцией.
 *
 * Не на странице: contacts.socials и catalog.low_stock_threshold — их пока ничто не
 * показывает (соцсети — со страницами в подвале, порог «мало» — с числовыми остатками API).
 */
final class SaveSettings
{
    public const string TEXT = 'text';

    public const string INTEGER = 'integer';

    public const string DECIMAL = 'decimal';

    public const string BOOLEAN = 'boolean';

    /**
     * The keys of the page and how each value is stored.
     */
    public const array KEYS = [
        'site.name' => self::TEXT,
        'contacts.phones' => self::TEXT,
        'contacts.email' => self::TEXT,
        'contacts.address' => self::TEXT,
        'contacts.schedule' => self::TEXT,
        'seller.requisites' => self::TEXT,
        'seller.vat_mode' => self::TEXT,
        'pickup.address' => self::TEXT,
        'delivery.free_city_from' => self::INTEGER,
        'catalog.discontinued_after_runs' => self::INTEGER,
        'catalog.local_warehouse_name' => self::TEXT,
        'catalog.local_strip_min_products' => self::INTEGER,
        'pricing.max_discount_without_purchase' => self::DECIMAL,
        'pricing.min_margin_percent' => self::DECIMAL,
        'pricing.show_tier_name' => self::BOOLEAN,
        'notify.telegram_include_contacts' => self::BOOLEAN,
        'payments.online_enabled' => self::BOOLEAN,
        'analytics.metrika_id' => self::TEXT,
        'seo.product_title_template' => self::TEXT,
        'seo.category_title_template' => self::TEXT,
    ];

    public function __construct(private readonly Settings $settings) {}

    /**
     * @param  array<string, mixed>  $values  key => value from the form; keys not on the page are ignored
     */
    public function handle(array $values): void
    {
        DB::transaction(function () use ($values): void {
            foreach (self::KEYS as $key => $type) {
                if (array_key_exists($key, $values)) {
                    Setting::query()->updateOrCreate(['key' => $key], ['value' => self::cast($values[$key], $type)]);
                }
            }
        });

        $this->settings->forget();
    }

    /**
     * The current values of the page's keys, one query.
     *
     * @return array<string, mixed>
     */
    public function current(): array
    {
        $keys = array_keys(self::KEYS);
        $this->settings->preload(...$keys);

        return array_combine($keys, array_map(fn (string $key): mixed => $this->settings->get($key), $keys));
    }

    /**
     * A percentage stays a decimal string, «10» or «12.5», read by Settings::percent(): no float.
     */
    private static function decimal(string $value): string
    {
        $value = str_replace(',', '.', $value);

        return str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
    }

    private static function cast(mixed $value, string $type): mixed
    {
        if ($type === self::BOOLEAN) {
            return (bool) $value;
        }

        if (is_string($value)) {
            // Line breaks of a textarea come as \r\n from browsers; one kind is kept.
            $value = trim(str_replace("\r\n", "\n", $value));
        }

        if ($value === null || $value === '') {
            return null;
        }

        return match ($type) {
            self::INTEGER => (int) $value,
            self::DECIMAL => self::decimal((string) $value),
            default => (string) $value,
        };
    }
}
