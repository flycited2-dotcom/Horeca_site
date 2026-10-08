@props(['price' => null, 'size' => 'card'])

{{--
    Цена (ТЗ §7, §9, облик «Свечение», макет — экраны 2 и 16). Сумма — крупно, шрифтом цен макета (gl-price) и
    табличными цифрами. Нет цены — «Цена по запросу» и что будет дальше, а не пустое место. Оптовику
    розничная цена показывается зачёркнутой, его собственная — с плашкой «Ваша цена»; акция в рознице —
    старая цена над новой.
--}}
@php
    $page = $size === 'page';
    $amountClass = $page ? 'gl-price gl-price--page' : 'gl-price gl-price--card';
@endphp

<div {{ $attributes->class('flex flex-col gap-1') }}>
    @if ($price === null)
        <span @class(['gl-price-ask', 'gl-price-ask--page' => $page])>{{ __('shop.price.on_request') }}</span>
        <span class="text-sm text-steel-500">{{ __('shop.price.on_request_note') }}</span>
    @elseif ($price->isWholesale && $price->hasDiscount())
        <span class="text-base text-steel-500 line-through tabular">{{ \App\Support\Typography::money($price->retail) }}</span>
        <span class="flex flex-wrap items-baseline gap-2">
            <span class="{{ $amountClass }}">{{ \App\Support\Typography::money($price->amount) }}</span>
            <span class="gl-yours">
                {{ __('shop.price.yours') }}@if ($price->tierName), {{ $price->tierName }}@endif
            </span>
        </span>
    @else
        @if ($price->oldPrice)
            <span class="text-base text-steel-500 line-through tabular">{{ \App\Support\Typography::money($price->oldPrice) }}</span>
        @endif
        <span class="{{ $amountClass }}">{{ \App\Support\Typography::money($price->amount) }}</span>
    @endif
</div>
