<?php

namespace App\Services\Catalog;

use App\Enums\AttributeType;
use App\Support\Typography;

/**
 * One characteristic in the filter panel (TZ §8.2): a number is chosen by a range «от — до»
 * with the section's smallest and largest value as hints, a text — by ticking values, each
 * with the number of products it leaves under the other filters.
 */
final readonly class AttributeFacet
{
    /**
     * @param  list<array{value: string, count: int, selected: bool}>  $options  values of a text characteristic
     * @param  array{min?: string, max?: string, values?: list<string>}  $condition  what the customer chose
     */
    public function __construct(
        public string $slug,
        public string $name,
        public ?string $unit,
        public AttributeType $type,
        public array $options = [],
        public ?string $min = null,
        public ?string $max = null,
        public array $condition = [],
    ) {}

    public function isRange(): bool
    {
        return $this->type === AttributeType::Number;
    }

    /**
     * «Мощность, Вт» — the heading of the group.
     */
    public function label(): string
    {
        return $this->unit ? $this->name.', '.$this->unit : $this->name;
    }

    /**
     * «Мощность: 1 000 — 3 000 Вт», «Количество конфорок: 4, 6».
     *
     * @param  array{min?: string, max?: string, values?: list<string>}  $condition
     */
    public static function chipLabel(string $name, ?string $unit, array $condition): string
    {
        if (isset($condition['values'])) {
            $value = implode(', ', $condition['values']);
        } else {
            $from = isset($condition['min']) ? Typography::decimal($condition['min']) : null;
            $to = isset($condition['max']) ? Typography::decimal($condition['max']) : null;

            $value = match (true) {
                $from !== null && $to !== null => __('shop.catalog.chip_attribute_range', ['from' => $from, 'to' => $to]),
                $from !== null => __('shop.catalog.chip_attribute_from', ['from' => $from]),
                default => __('shop.catalog.chip_attribute_to', ['to' => (string) $to]),
            };

            $value .= $unit ? Typography::NBSP.$unit : '';
        }

        return __('shop.catalog.chip_attribute', ['name' => $name, 'value' => $value]);
    }
}
