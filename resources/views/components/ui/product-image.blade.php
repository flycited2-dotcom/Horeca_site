@props([
    'product',
    'conversion' => 'card',
    'ratio' => 'aspect-[4/3]',
    'iconClass' => 'size-11',
    'withType' => false,
    'plate' => false,
    'zone' => null,
])

{{--
    Фото товара или заглушка (ТЗ §9, облик «Свечение», макет — экраны 1 и 2): пиктограмма типа оборудования
    на фоне полотна, а не пустой прямоугольник. Заглушка рисуется без сетевых запросов;
    в листинге под пиктограммой подписан тип — «Тепловое», «Холодильное».
    С $plate фото лежит на светлой плашке (gl-plate) с мягким свечением зоны: снимки поставщика сняты на
    белом фоне и ничем не обрабатываются. Цвет свечения плашка берёт у карточки, в которой лежит; $zone
    (App\Support\CategoryZone) задаёт его, когда карточки вокруг нет: строка поиска, корзина.
--}}
@php
    use App\Support\CategoryZone;

    $url = $product->getFirstMediaUrl(\App\Models\Product::IMAGES, $conversion) ?: null;
    $icon = $product->category?->icon;
    $type = $withType && $icon !== null && \Illuminate\Support\Facades\Lang::has("shop.equipment.{$icon}") ? __("shop.equipment.{$icon}") : null;
@endphp

@if ($plate)
    <div {{ $attributes->class([
        'gl-plate gl-pimg',
        'gl-pimg--photo' => $url,
        'gl-cold' => $zone === CategoryZone::COLD,
        'gl-hot' => $zone === CategoryZone::HOT,
        'gl-neutral' => $zone === CategoryZone::NEUTRAL,
        $ratio,
    ]) }}>
        @if ($url)
            <img src="{{ $url }}" alt="{{ $product->name }}" loading="lazy" decoding="async">
        @else
            <x-ui.equipment-icon :icon="$icon" class="{{ $iconClass }} relative z-1 text-steel-400" />
            <span class="sr-only">{{ __('shop.product.no_photo') }}</span>

            @if ($type)
                <span class="gl-pimg__type max-md:hidden" aria-hidden="true">{{ $type }}</span>
            @endif
        @endif
    </div>
@else
    <div {{ $attributes->class(['relative flex items-center justify-center overflow-hidden', $url ? 'bg-stage' : 'bg-bg', $ratio]) }}>
        @if ($url)
            <img src="{{ $url }}" alt="{{ $product->name }}" loading="lazy" class="h-full w-full object-contain">
        @else
            <x-ui.equipment-icon :icon="$icon" class="{{ $iconClass }} text-steel-400" />
            <span class="sr-only">{{ __('shop.product.no_photo') }}</span>

            @if ($type)
                <span class="absolute bottom-3 left-3 rounded-xs bg-line-soft max-md:hidden px-1.75 py-1 text-xs leading-[1.3] font-medium text-ink" aria-hidden="true">{{ $type }}</span>
            @endif
        @endif
    </div>
@endif
