@props(['line'])

{{--
    Строка корзины (ТЗ §10.1, макет — экран 6): фото или заглушка, бренд и артикул,
    название, статус, счётчик, цена за единицу и сумма, удаление. «Под заказ» — подпись
    про срок; изменившаяся цена — прежняя рядом с новой; позиция, которую больше нельзя
    купить, подсвечена и держит оформление, пока её не удалят. Формы работают без
    скриптов (CartController), Livewire перехватывает их на месте.
--}}
@php
    use App\Support\Typography;

    $product = $line->product;
    $id = $product->id;
@endphp

<li
    wire:key="line-{{ $id }}"
    @class([
        'grid grid-cols-[64px_minmax(0,1fr)] gap-x-3 gap-y-3 p-3 md:grid-cols-[80px_minmax(0,1fr)_auto_140px_auto] md:items-center md:gap-x-5 md:p-4',
        'border-l-4 border-l-danger bg-incoming-bg/40' => $line->block,
    ])
>
    <a href="{{ route('product', $product) }}" tabindex="-1" aria-hidden="true" class="md:self-start">
        <x-ui.product-image :product="$product" conversion="thumb" ratio="aspect-square rounded-card border border-line-soft" icon-class="size-7" />
    </a>

    <div class="flex min-w-0 flex-col gap-1.5">
        <p class="flex flex-wrap items-baseline gap-x-2.5 text-sm">
            @if ($product->brand)
                <span class="font-medium">{{ $product->brand->name }}</span>
            @endif
            @if ($product->sku)
                <x-ui.data :label="__('shop.product.sku')">{{ $product->sku }}</x-ui.data>
            @endif
        </p>

        <a href="{{ route('product', $product) }}" class="text-base font-semibold transition-colors duration-150 ease-out hover:text-accent-ink md:text-md">{{ $product->name }}</a>

        @if ($line->block)
            <p class="text-sm font-medium text-danger-text">{{ $line->block->lineMessage() }}</p>
        @else
            <x-ui.availability :availability="$product->availability" class="self-start" />
            @if ($line->onOrder())
                <p class="text-sm text-steel-500">{{ __('shop.product.on_order_note') }}</p>
            @endif
        @endif

        @if ($line->previousPrice)
            <p class="text-sm text-incoming">
                {{ __('shop.cart.price_changed', ['old' => Typography::money($line->previousPrice), 'new' => Typography::money($line->unitPrice())]) }}
            </p>
        @endif
    </div>

    @if ($line->block)
        <div class="col-span-2 md:col-span-2 md:col-start-3"></div>
    @else
        <form
            method="post"
            action="{{ route('cart.update', $id) }}"
            wire:submit="setQuantity({{ $id }}, $event.target.elements.quantity.value)"
            x-on:change="$el.requestSubmit()"
            class="col-start-2 flex items-center gap-2 md:col-start-auto"
        >
            @csrf
            @method('PATCH')
            <x-ui.counter name="quantity" :value="$line->quantity()" :min="0" id="cart-quantity-{{ $id }}" :label="__('shop.counter.label').': '.$product->name" />
            <button type="submit" class="no-js-only tap-target text-sm font-medium text-accent-ink">{{ __('shop.cart.recalculate') }}</button>
        </form>

        <div class="col-start-2 flex items-baseline justify-between gap-3 md:col-start-auto md:flex-col md:items-end md:gap-0.5">
            <span class="text-sm text-steel-500 tabular">
                @if ($line->price?->isWholesale && $line->price->hasDiscount())
                    {{-- Оптовику — розничная зачёркнутой рядом со своей (ТЗ §7). --}}
                    <s aria-label="{{ __('shop.price.retail_per_unit') }}">{{ Typography::money($line->price->retail) }}</s>
                @endif
                {{ __('shop.cart.per_unit', ['price' => Typography::money($line->unitPrice()), 'unit' => $product->unit]) }}
            </span>
            <span class="text-lg font-bold whitespace-nowrap tabular">{{ Typography::money($line->sum()) }}</span>
        </div>
    @endif

    <form
        method="post"
        action="{{ route('cart.remove', $id) }}"
        wire:submit="remove({{ $id }})"
        @class(['col-start-2 md:col-start-auto', 'max-md:row-start-3' => $line->block])
    >
        @csrf
        @method('DELETE')
        @if ($line->block)
            <x-ui.button type="submit" variant="neutral" class="text-sm">{{ __('shop.cart.remove') }}</x-ui.button>
        @else
            <button type="submit" class="tap-target flex size-8 items-center justify-center rounded-control text-steel-500 transition-colors duration-150 ease-out hover:text-danger-text">
                <span class="sr-only">{{ __('shop.cart.remove_named', ['name' => $product->name]) }}</span>
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        @endif
    </form>
</li>
