<?php

namespace App\Actions\Catalog;

use App\Enums\AttributeType;
use App\Enums\AttributeValueSource;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Supplier\Data\SupplierProductDetails;
use App\Services\Supplier\Import\AttributeValueParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Описание, габариты, вес, гарантия и характеристики товара от поставщика (ТЗ §6; решение
 * заказчика от 24.09.2026 — со списка сайта Росхолода, пока нет API). Этими полями владеет
 * источник содержимого: каталог XML заполняет описание только у нового товара.
 *
 * Ручное священно (§6.6): поле из `locked_fields` и характеристика, которую менеджер задал сам
 * (`source = manual`), не меняются. Чего источник не сообщил, то остаётся как было; характеристика
 * поставщика, пропавшая у товара, снимается. Новая характеристика создаётся нефильтруемой —
 * в фильтры её включает менеджер.
 */
final class SyncSupplierDetails
{
    public const array OWNED_FIELDS = ['description', 'length_mm', 'width_mm', 'height_mm', 'weight_kg', 'warranty_months'];

    /**
     * Characteristics already read or created in this process, by slug.
     *
     * @var array<string, Attribute>
     */
    private array $attributes = [];

    public function __construct(private readonly AttributeValueParser $parser) {}

    public function handle(Supplier $supplier, SupplierProductDetails $details): ContentSyncResult
    {
        $product = Product::query()
            ->where('supplier_id', $supplier->id)
            ->where('external_id', $details->externalId)
            ->first();

        if ($product === null) {
            return ContentSyncResult::NoProduct;
        }

        $locked = array_flip($product->locked_fields ?? []);
        $values = [
            'description' => $details->description,
            'length_mm' => $details->lengthMm,
            'width_mm' => $details->widthMm,
            'height_mm' => $details->heightMm,
            'weight_kg' => $details->weightKg === null ? null : self::weight($details->weightKg),
            'warranty_months' => $details->warrantyMonths,
        ];

        foreach ($values as $column => $value) {
            if ($value !== null && ! isset($locked[$column])) {
                $product->setAttribute($column, $value);
            }
        }

        $changed = $product->isDirty();

        if ($changed) {
            $product->save();
        }

        return $this->syncAttributes($product, $details) || $changed ? ContentSyncResult::Updated : ContentSyncResult::Unchanged;
    }

    /**
     * @return bool whether any value changed
     */
    private function syncAttributes(Product $product, SupplierProductDetails $details): bool
    {
        $wanted = [];

        foreach ($details->attributes as $supplied) {
            $parsed = $this->parser->parse($supplied);

            if ($parsed['slug'] === '' || $parsed['text'] === '') {
                continue;
            }

            $attribute = $this->attribute($parsed);
            $number = $attribute->type === AttributeType::Number ? $parsed['number'] : null;

            $wanted[$attribute->id] = [
                'value_string' => $attribute->type === AttributeType::Number ? null : Str::limit($parsed['text'], 255, ''),
                'value_number' => $number,
                'value_bool' => null,
                'raw_value' => Str::limit($parsed['text'], 255, ''),
            ];
        }

        $current = DB::table('attribute_product')->where('product_id', $product->id)->get()->keyBy('attribute_id');
        $changed = false;

        foreach ($current as $attributeId => $row) {
            if ($row->source === AttributeValueSource::Supplier->value && ! isset($wanted[$attributeId])) {
                DB::table('attribute_product')->where('product_id', $product->id)->where('attribute_id', $attributeId)->delete();
                $changed = true;
            }
        }

        foreach ($wanted as $attributeId => $value) {
            $row = $current->get($attributeId);

            if ($row !== null && $row->source === AttributeValueSource::Manual->value) {
                continue;
            }

            if ($row !== null && self::same($row, $value)) {
                continue;
            }

            DB::table('attribute_product')->updateOrInsert(
                ['product_id' => $product->id, 'attribute_id' => $attributeId],
                $value + ['source' => AttributeValueSource::Supplier->value],
            );
            $changed = true;
        }

        return $changed;
    }

    /**
     * @param  array{name: string, unit: ?string, slug: string, number: ?string, text: string}  $parsed
     */
    private function attribute(array $parsed): Attribute
    {
        return $this->attributes[$parsed['slug']] ??= Attribute::query()->firstOrCreate(
            ['slug' => $parsed['slug']],
            [
                'name' => Str::limit($parsed['name'], 150, ''),
                'unit' => $parsed['unit'] === null ? null : Str::limit($parsed['unit'], 16, ''),
                'type' => $parsed['number'] !== null ? AttributeType::Number : AttributeType::Text,
                'is_filterable' => false,
                'is_main' => false,
                'sort' => 100,
            ],
        );
    }

    /**
     * @param  array{value_string: ?string, value_number: ?string, value_bool: null, raw_value: string}  $value
     */
    private static function same(object $row, array $value): bool
    {
        return $row->value_string === $value['value_string']
            && self::decimal($row->value_number) === self::decimal($value['value_number'])
            && $row->raw_value === $value['raw_value'];
    }

    /**
     * «1350.000» and «1350» are the same number; «0.000» is «0».
     */
    private static function decimal(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;

        return str_contains($value, '.') ? (rtrim(rtrim($value, '0'), '.') ?: '0') : $value;
    }

    /**
     * «46» → «46.000»: the column keeps three decimals, so an unchanged weight is not a change.
     */
    private static function weight(string $kilograms): string
    {
        [$whole, $fraction] = array_pad(explode('.', $kilograms, 2), 2, '');

        return $whole.'.'.str_pad(substr($fraction, 0, 3), 3, '0');
    }
}
