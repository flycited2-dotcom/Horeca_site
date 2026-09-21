@props(['product', 'price' => null, 'exact' => false])

{{--
    Строка результата поиска (макет, экран 8): заглушка, бренд и артикул, название, модель
    и код 1С моноширинным, статус, цена справа и кнопка. Точное совпадение по артикулу —
    крупнее, в рамке accent и с кнопкой «Открыть карточку». На телефоне строка —
    карточка: цена и кнопка под названием.
--}}
@php
    $url = route('product', $product);
    // В выгрузке поставщика «модель» часто повторяет название — тогда её не показываем.
    $model = $product->model !== null && mb_strlen($product->model) <= 32
        && ! str_contains(mb_strtolower($product->name), mb_strtolower($product->model))
        ? $product->model
        : null;
    $codes = array_filter([$model, $product->supplier_code ? __('shop.product.supplier_code').' '.$product->supplier_code : null]);
@endphp

<article {{ $attributes->class([
    'grid items-start gap-3 bg-surface p-3 md:items-center md:gap-5 md:p-4',
    'grid-cols-[56px_minmax(0,1fr)] md:grid-cols-[96px_minmax(0,1fr)_200px_220px] rounded-card border border-accent shadow-raised' => $exact,
    'grid-cols-[56px_minmax(0,1fr)] md:grid-cols-[80px_minmax(0,1fr)_170px_200px] max-md:rounded-card max-md:border max-md:border-line md:border-b md:border-line-soft' => ! $exact,
]) }}>
    <a href="{{ $url }}" tabindex="-1" aria-hidden="true">
        <x-ui.product-image :product="$product" conversion="thumb" ratio="aspect-square rounded-card border border-line md:aspect-[4/3]" icon-class="size-6.5 md:size-8" />
    </a>

    <div class="flex min-w-0 flex-col gap-1.5">
        @if ($exact)
            <span class="self-start rounded-xs border border-accent-line bg-accent-soft px-1.75 py-0.75 text-xs leading-[1.3] font-medium text-accent-ink">
                <span class="md:hidden">{{ __('shop.search.exact_short') }}</span>
                <span class="max-md:hidden">{{ __('shop.search.exact') }}</span>
            </span>
        @endif

        <p class="flex flex-wrap items-baseline gap-x-2.5 text-sm">
            @if ($product->brand)
                <span class="font-medium">{{ $product->brand->name }}</span>
            @endif
            @if ($product->sku)
                <x-ui.data :label="__('shop.product.sku')">{{ $product->sku }}</x-ui.data>
            @endif
        </p>

        <h3 @class(['font-semibold', 'text-md md:text-title' => $exact, 'text-base md:text-lg' => ! $exact])>
            <a href="{{ $url }}" class="transition-colors duration-150 ease-out hover:text-accent-ink">{{ $product->name }}</a>
        </h3>

        @if ($codes !== [])
            <x-ui.data class="max-md:hidden">{{ implode(' · ', $codes) }}</x-ui.data>
        @endif

        <x-ui.availability :availability="$product->availability" class="max-md:hidden" />
        <x-ui.availability :availability="$product->availability" variant="text" class="md:hidden" />

        <div class="flex items-center gap-2 pt-0.5 md:hidden">
            <span class="text-title font-bold whitespace-nowrap tabular">
                {{ $price === null ? __('shop.price.on_request') : \App\Support\Typography::money($price->amount) }}
            </span>
            <x-ui.button :variant="$exact ? 'primary' : 'secondary'" class="ml-auto whitespace-nowrap">
                {{ $price === null ? __('shop.product.request_price') : __('shop.product.add_to_cart_short') }}
            </x-ui.button>
        </div>
    </div>

    <x-ui.price :price="$price" class="items-end text-right max-md:hidden" />

    <div class="flex flex-col gap-2 max-md:hidden">
        @if ($price === null)
            <x-ui.button variant="secondary" class="w-full">{{ __('shop.product.request_price') }}</x-ui.button>
        @else
            <x-ui.button class="w-full">{{ __('shop.product.add_to_cart_short') }}</x-ui.button>
        @endif

        @if ($exact)
            <x-ui.button variant="neutral" :href="$url" class="w-full">{{ __('shop.search.open_product') }}</x-ui.button>
        @endif
    </div>
</article>
