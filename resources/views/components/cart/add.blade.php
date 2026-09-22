@props([
    'product',
    'label' => null,
    'variant' => 'primary',
    'counter' => false,
    'counterId' => null,
    'counterClass' => '',
    'buttonClass' => '',
    'formId' => null,
])

{{--
    «В корзину» (ТЗ §6.5, §10.1): обычная POST-форма — без скриптов страница вернётся
    с уведомлением, скрипт витрины кладёт товар без перезагрузки и обновляет корзину
    в шапке. $counter — счётчик количества в самой форме (карточка товара, липкая полоса);
    $formId — id формы, когда счётчик стоит в другой ячейке строки таблицы (x-ui.counter
    с тем же form). Без счётчика кладётся одна штука. Выводится только для товаров,
    которые можно купить; остальным кнопки нет.
--}}
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
