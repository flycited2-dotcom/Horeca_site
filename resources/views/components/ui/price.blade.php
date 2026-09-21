@props(['price' => null, 'size' => 'card'])

{{--
    Цена (ТЗ §7, §9, макет — экраны 2 и 16). Нет цены — «Цена по запросу» и что будет
    дальше, а не пустое место. Оптовику розничная цена показывается зачёркнутой, его
    собственная — с плашкой «Ваша цена»; акция в рознице — старая цена над новой.
--}}
@php
    $page = $size === 'page';
    $amountClass = $page ? 'text-2xl' : 'text-title md:text-xl';
@endphp

<div {{ $attributes->class('flex flex-col gap-1') }}>
    @if ($price === null)
        <span @class(['font-semibold', 'text-2xl font-bold' => $page, 'text-title' => ! $page])>{{ __('shop.price.on_request') }}</span>
        <span class="text-sm text-steel-500">{{ __('shop.price.on_request_note') }}</span>
    @elseif ($price->isWholesale && $price->hasDiscount())
        <span class="text-base text-steel-500 line-through tabular">{{ \App\Support\Typography::money($price->retail) }}</span>
        <span class="flex flex-wrap items-baseline gap-2">
            <span class="{{ $amountClass }} font-bold tabular">{{ \App\Support\Typography::money($price->amount) }}</span>
            <span class="rounded-xs border border-accent-line bg-accent-soft px-1.5 py-0.75 text-xs leading-[1.3] font-medium text-accent-ink">
                {{ __('shop.price.yours') }}@if ($price->tierName), {{ $price->tierName }}@endif
            </span>
        </span>
    @else
        @if ($price->oldPrice)
            <span class="text-base text-steel-500 line-through tabular">{{ \App\Support\Typography::money($price->oldPrice) }}</span>
        @endif
        <span class="{{ $amountClass }} font-bold tabular">{{ \App\Support\Typography::money($price->amount) }}</span>
    @endif
</div>
