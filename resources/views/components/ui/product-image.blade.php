@props(['product', 'conversion' => 'card', 'ratio' => 'aspect-[4/3]'])

{{--
    Фото товара или заглушка (ТЗ §9): пиктограмма типа оборудования на фоне полотна,
    а не пустой прямоугольник. Заглушка рисуется без сетевых запросов.
--}}
@php
    $url = $product->getFirstMediaUrl(\App\Models\Product::IMAGES, $conversion) ?: null;
    $icon = $product->category?->icon;
@endphp

<div {{ $attributes->class(['flex items-center justify-center overflow-hidden rounded-card bg-bg', $ratio]) }}>
    @if ($url)
        <img src="{{ $url }}" alt="{{ $product->name }}" loading="lazy" class="h-full w-full object-contain">
    @else
        <x-ui.equipment-icon :icon="$icon" class="size-11 text-steel-400" />
        <span class="sr-only">{{ __('shop.product.no_photo') }}</span>
    @endif
</div>
