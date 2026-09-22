@props(['product'])

{{--
    «Запросить цену» в листинге, строке поиска, таблице и «Часто берут вместе» (ТЗ §6.5):
    открывает общее окно каркаса, скрипт подставляет в него этот товар. Без скриптов окно
    открывается тоже — менеджер получит заявку, товар можно назвать в сообщении.
--}}
<x-ui.button
    variant="secondary"
    popovertarget="lead-price-shared"
    data-lead-product-id="{{ $product->id }}"
    data-lead-product-name="{{ $product->name }}"
    {{ $attributes }}
>{{ __('shop.product.request_price') }}</x-ui.button>
