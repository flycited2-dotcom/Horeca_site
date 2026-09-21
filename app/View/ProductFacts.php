<?php

namespace App\View;

use App\Models\Attribute;
use App\Models\Product;
use App\Support\Typography;

/**
 * The facts of a product page as «ключ → значение» rows (TZ §8.3, layout — screen 3): the
 * characteristics table and «Проверьте перед монтажом». Values follow the data typography
 * of TZ §9: «840×800×1120 мм», «18,5 кг», codes in monospace.
 */
final readonly class ProductFacts
{
    /**
     * A model longer than this is usually the supplier's full name again, not a model.
     */
    private const int MODEL_LENGTH = 32;

    public function __construct(private Product $product) {}

    /**
     * @return list<array{label: string, value: string, mono: bool}>
     */
    public function specs(): array
    {
        $product = $this->product;

        $rows = [
            $this->row(__('shop.product.brand'), $product->brand?->name),
            $this->row(__('shop.product.model'), $this->model(), mono: true),
            $this->row(__('shop.product.sku'), $product->sku, mono: true),
            $this->row(__('shop.product.supplier_code'), $product->supplier_code, mono: true),
            $this->row(__('shop.product.dimensions'), $this->dimensions(), mono: true),
            $this->row(__('shop.product.weight'), $this->weight()),
        ];

        foreach ($product->attributeValues as $attribute) {
            $rows[] = $this->row($attribute->name, $this->attributeValue($attribute), mono: $attribute->pivot->value_number !== null);
        }

        $rows[] = $this->row(__('shop.product.unit'), $product->unit);

        return array_values(array_filter($rows));
    }

    /**
     * What a buyer checks before the installation: dimensions, weight and the main
     * characteristics (connection, water). Empty when the supplier gave none of them.
     *
     * @return list<array{label: string, value: string, mono: bool}>
     */
    public function installCheck(): array
    {
        $rows = [
            $this->row(__('shop.product.dimensions'), $this->dimensions(), mono: true),
            $this->row(__('shop.product.weight'), $this->weight()),
        ];

        foreach ($this->product->attributeValues->where('is_main', true) as $attribute) {
            $rows[] = $this->row($attribute->name, $this->attributeValue($attribute), mono: $attribute->pivot->value_number !== null);
        }

        return array_values(array_filter($rows));
    }

    public function dimensions(): ?string
    {
        $sizes = [$this->product->length_mm, $this->product->width_mm, $this->product->height_mm];

        if (in_array(null, $sizes, true)) {
            return null;
        }

        return implode('×', array_map(fn (int $size): string => (string) $size, $sizes)).Typography::NBSP.'мм';
    }

    public function weight(): ?string
    {
        return $this->product->weight_kg === null ? null : self::decimal((string) $this->product->weight_kg).Typography::NBSP.'кг';
    }

    private function model(): ?string
    {
        $model = $this->product->model;

        if ($model === null || mb_strlen($model) > self::MODEL_LENGTH || str_contains(mb_strtolower($this->product->name), mb_strtolower($model))) {
            return null;
        }

        return $model;
    }

    private function attributeValue(Attribute $attribute): ?string
    {
        $pivot = $attribute->pivot;

        $value = match (true) {
            filled($pivot->value_string) => (string) $pivot->value_string,
            $pivot->value_number !== null => self::decimal((string) $pivot->value_number),
            $pivot->value_bool !== null => $pivot->value_bool ? __('shop.product.yes') : __('shop.product.no'),
            filled($pivot->raw_value) => (string) $pivot->raw_value,
            default => null,
        };

        if ($value === null) {
            return null;
        }

        return $attribute->unit && $pivot->value_number !== null ? $value.Typography::NBSP.$attribute->unit : $value;
    }

    /**
     * «18.500» → «18,5»: no float ever touches the value.
     */
    private static function decimal(string $value): string
    {
        if (str_contains($value, '.')) {
            $value = rtrim(rtrim($value, '0'), '.');
        }

        return str_replace('.', ',', $value);
    }

    /**
     * @return array{label: string, value: string, mono: bool}|null
     */
    private function row(string $label, ?string $value, bool $mono = false): ?array
    {
        return $value === null || trim($value) === '' ? null : ['label' => $label, 'value' => trim($value), 'mono' => $mono];
    }
}
