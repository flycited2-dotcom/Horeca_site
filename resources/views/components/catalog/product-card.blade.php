@props(['product', 'price' => null])

{{--
    Карточка листинга (ТЗ §8.2, макет — экраны 2 и 14). С 768 px — вертикальная: фото или
    заглушка 4:3 впритык к краям, бренд и артикул, название до трёх строк, статус, цена,
    кнопка по §6.5. На телефоне — горизонтальная: заглушка 96×96 слева, цена и статус
    в одну строку, без кнопки — карточка открывается нажатием на название.
    Тень только на наведении, сдвига нет.
--}}
<article {{ $attributes->class('flex h-full gap-3 overflow-hidden rounded-card border border-line bg-surface p-3 transition-shadow duration-150 ease-out hover:shadow-raised md:flex-col md:gap-0 md:p-0') }}>
    <a href="{{ route('product', $product) }}" tabindex="-1" aria-hidden="true" class="shrink-0">
        <x-ui.product-image
            :product="$product"
            ratio="size-24 rounded-card md:size-auto md:w-full md:aspect-[4/3] md:rounded-none md:border-b md:border-line"
            icon-class="size-10 md:size-16"
            :with-type="true"
        />
    </a>

    <div class="flex min-w-0 flex-1 flex-col gap-1.5 md:gap-2.5 md:p-4">
        <p class="flex items-baseline justify-between gap-2 text-sm font-medium">
            <span class="truncate">{{ $product->brand?->name }}</span>
            @if ($product->sku)
                <x-ui.data :label="__('shop.product.sku')" class="shrink-0">{{ $product->sku }}</x-ui.data>
            @endif
        </p>

        <h3 class="line-clamp-3 text-md font-semibold md:text-lg">
            <a href="{{ route('product', $product) }}" class="transition-colors duration-150 ease-out hover:text-accent-ink">{{ $product->name }}</a>
        </h3>

        <x-ui.availability :availability="$product->availability" class="max-md:hidden" />

        <div class="mt-auto flex flex-wrap items-end justify-between gap-2 pt-1 md:flex-col md:items-stretch md:gap-2.5 md:pt-3">
            <x-ui.price :price="$price" />
            <x-ui.availability :availability="$product->availability" class="md:hidden" />

            @if ($price === null)
                <x-ui.button variant="secondary" class="w-full max-md:hidden">{{ __('shop.product.request_price') }}</x-ui.button>
            @else
                <x-ui.button class="w-full max-md:hidden">{{ __('shop.product.add_to_cart_short') }}</x-ui.button>
            @endif
        </div>
    </div>
</article>
