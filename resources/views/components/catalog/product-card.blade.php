@props(['product', 'price' => null])

{{--
    Карточка листинга (макет, экран 2): заглушка 4:3 → бренд · артикул → название →
    две ключевые характеристики → цена → статус → кнопка. Тень только на наведении,
    сдвига нет.
--}}
<article {{ $attributes->class('flex h-full flex-col gap-2.5 rounded-card border border-line bg-surface p-4 transition-shadow duration-150 ease-out hover:shadow-raised') }}>
    <a href="{{ route('product', $product) }}" tabindex="-1" aria-hidden="true">
        <x-ui.product-image :product="$product" />
    </a>

    <p class="flex flex-wrap items-baseline gap-x-2 text-sm">
        @if ($product->brand)
            <span class="font-medium text-ink">{{ $product->brand->name }}</span>
        @endif
        @if ($product->sku)
            <x-ui.data :label="__('shop.product.sku')">{{ $product->sku }}</x-ui.data>
        @endif
    </p>

    <h3 class="text-lg font-semibold">
        <a href="{{ route('product', $product) }}" class="transition-colors duration-150 ease-out hover:text-accent-ink">
            {{ $product->name }}
        </a>
    </h3>

    @if ($product->model)
        <x-ui.data :label="__('shop.product.model')">{{ $product->model }}</x-ui.data>
    @endif

    <div class="mt-auto flex flex-col gap-2 pt-2">
        <x-ui.price :price="$price" />
        <x-ui.availability :availability="$product->availability" />

        @if ($price === null)
            <x-ui.button variant="secondary" class="w-full">{{ __('shop.product.request_price') }}</x-ui.button>
        @else
            <x-ui.button class="w-full">{{ __('shop.product.add_to_cart_short') }}</x-ui.button>
        @endif
    </div>
</article>
