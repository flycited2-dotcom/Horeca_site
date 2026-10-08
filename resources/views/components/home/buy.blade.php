@props(['item', 'round' => false])

{{--
    Кнопка покупки на карточке главной (ТЗ §6.5): с ценой — обычная форма «В корзину» (x-cart.add: скрипт витрины
    кладёт товар без перезагрузки), без цены — «Запросить цену» в общем окне каркаса. Облик кнопкам даёт
    x-ui.button (gl-btn). $round — круглая кнопка с одной корзиной (gl-round, поверх gl-btn): подпись
    «Добавить в корзину: название» остаётся для чтения с экрана, но скрыта визуально. gl-buy и gl-ask — крючки
    для стилей главной (resources/css/glow-home.css): кнопка лежит над растянутой ссылкой карточки.
--}}
@php
    use App\View\HomeIcon;
    use Illuminate\Support\HtmlString;

    $product = $item['product'];

    $label = $round
        ? new HtmlString(HomeIcon::svg('cart').'<span class="sr-only">'.e(__('shop.product.add_to_cart').': '.$item['name']).'</span>')
        : new HtmlString(HomeIcon::svg('cart').e(__('shop.product.add_to_cart_short')));
@endphp

@if ($item['price'] === null)
    <x-lead.request-price :product="$product" class="gl-ask" />
@else
    <x-cart.add
        :product="$product"
        :label="$label"
        :button-class="$round ? 'gl-round gl-round--cart' : ''"
        class="gl-buy"
    />
@endif
