@props([
    'product',
    'conversion' => 'card',
    'ratio' => 'aspect-[4/3]',
    'iconClass' => 'size-11',
    'withType' => false,
])

{{--
    Фото товара или заглушка (ТЗ §9, макет — экраны 1 и 2): пиктограмма типа оборудования
    на фоне полотна, а не пустой прямоугольник. Заглушка рисуется без сетевых запросов;
    в листинге под пиктограммой подписан тип — «Тепловое», «Холодильное».
--}}
@php
    $url = $product->getFirstMediaUrl(\App\Models\Product::IMAGES, $conversion) ?: null;
    $icon = $product->category?->icon;
    $type = $withType && $icon !== null && \Illuminate\Support\Facades\Lang::has("shop.equipment.{$icon}") ? __("shop.equipment.{$icon}") : null;
@endphp

<div {{ $attributes->class(['relative flex items-center justify-center overflow-hidden bg-bg', $ratio]) }}>
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
