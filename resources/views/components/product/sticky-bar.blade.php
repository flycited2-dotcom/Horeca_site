@props(['product', 'price' => null])

{{--
    Липкая полоса покупки ниже 1024 px (ТЗ §9, макет — экраны 3 и 14): цена, счётчик
    и кнопка под большим пальцем. Появляется, когда панель покупки ушла вверх за экран;
    без скриптов не нужна — панель покупки стоит в потоке страницы.
--}}
<div
    data-sticky-buy
    hidden
    {{ $attributes->class('fixed inset-x-0 bottom-0 z-30 border-t border-line bg-surface shadow-raised lg:hidden') }}
>
    <div class="container-page flex h-18 items-center gap-3">
        <div class="flex min-w-0 flex-1 flex-col">
            @if ($price === null)
                <span class="text-md font-semibold">{{ __('shop.price.on_request') }}</span>
            @else
                <span class="text-title font-bold whitespace-nowrap tabular">{{ \App\Support\Typography::money($price->amount) }}</span>
                @if ($price->isWholesale && $price->hasDiscount())
                    <span class="text-xs text-steel-500">{{ __('shop.price.yours') }}</span>
                @endif
            @endif
        </div>

        @if ($price === null)
            <x-ui.button variant="secondary">{{ __('shop.product.request_price') }}</x-ui.button>
        @else
            <x-ui.counter name="quantity_bar" id="bar-quantity" class="max-sm:hidden" />
            <x-ui.button>{{ __('shop.product.add_to_cart_short') }}</x-ui.button>
        @endif
    </div>
</div>
