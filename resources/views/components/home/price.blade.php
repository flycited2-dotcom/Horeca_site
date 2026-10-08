@props(['price' => null])

{{--
    Цена на карточке главной (ТЗ §7): ту же цену считает PriceResolver, что и в листингах. Оптовику
    розничная цена показывается зачёркнутой над его собственной, акция в рознице — старая цена над новой.
    Нет цены — «Цена по запросу».
--}}
@php
    use App\Support\Typography;

    $was = $price === null ? null : ($price->isWholesale && $price->hasDiscount() ? $price->retail : $price->oldPrice);
@endphp

<span {{ $attributes->class('gl-pricebox') }}>
    @if ($price === null)
        <span class="gl-price gl-price--ask">{{ __('shop.price.on_request') }}</span>
    @else
        @if ($was)
            <s class="gl-was">{{ Typography::money($was) }}</s>
        @endif
        <span class="gl-price">{{ Typography::money($price->amount) }}</span>
    @endif
</span>
