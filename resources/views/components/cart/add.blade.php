@props([
    'product',
    'label' => null,
    'variant' => 'primary',
    'counter' => false,
    'counterId' => null,
    'counterClass' => '',
    'buttonClass' => '',
    'formId' => null,
    'round' => false,
])

{{--
    «В корзину» (ТЗ §6.5, §10.1): обычная POST-форма — без скриптов страница вернётся
    с уведомлением, скрипт витрины кладёт товар без перезагрузки и обновляет корзину
    в шапке. $counter — счётчик количества в самой форме (карточка товара, липкая полоса);
    $formId — id формы, когда счётчик стоит в другой ячейке строки таблицы (x-ui.counter
    с тем же form). Без счётчика кладётся одна штука. $round — круглая оранжевая кнопка с одной корзиной
    (облик «Свечение», карточка листинга): подпись «Добавить в корзину: название» остаётся для чтения
    с экрана, но скрыта визуально. Выводится только для товаров, которые можно купить; остальным кнопки нет.
--}}
@php
    if ($round) {
        $label ??= new \Illuminate\Support\HtmlString(
            '<svg class="gl-ic" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9.5" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2.5 3.5h2.8l2.3 11.2h10.4l2-8.2H6.2"/></svg>'
            .'<span class="sr-only">'.e(__('shop.product.add_to_cart').': '.$product->name).'</span>'
        );
        $buttonClass = trim('gl-round gl-round--cart '.$buttonClass);
    }
@endphp

<form
    method="post"
    action="{{ route('cart.add', $product->id) }}"
    data-cart-form
    @if ($formId) id="{{ $formId }}" @endif
    {{ $attributes }}
>
    @csrf
    @if ($counter)
        <x-ui.counter name="quantity" :id="$counterId ?? 'quantity-'.$product->id" :label="__('shop.counter.label').', '.$product->unit" :class="$counterClass" />
    @endif
    <x-ui.button type="submit" :variant="$variant" :class="$buttonClass">{{ $label ?? __('shop.product.add_to_cart_short') }}</x-ui.button>
</form>
