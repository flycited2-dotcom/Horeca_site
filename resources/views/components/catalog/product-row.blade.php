@props(['product', 'price' => null, 'exact' => false])

{{--
    Строка результата поиска (облик «Свечение», макет — экран 8): плашка с фото, бренд и артикул, название,
    модель и код 1С моноширинным, статус, цена справа и кнопка. Ряды без свечения — их сравнивают глазами.
    Точное совпадение по артикулу — крупнее, стеклянной карточкой с тёплой светящейся рамкой и с кнопкой
    «Открыть карточку». На телефоне строка — карточка: цена и кнопка под названием.
--}}
@php
    use App\Support\CategoryZone;

    $url = route('product', $product);
    $zone = CategoryZone::of($product->category?->icon, $product->category?->name);
    // В выгрузке поставщика «модель» часто повторяет название — тогда её не показываем.
    $model = $product->model !== null && mb_strlen($product->model) <= 32
        && ! str_contains(mb_strtolower($product->name), mb_strtolower($product->model))
        ? $product->model
        : null;
    $codes = array_filter([$model, $product->supplier_code ? __('shop.product.supplier_code').' '.$product->supplier_code : null]);
@endphp

<article {{ $attributes->class([
    'gl-prow grid items-start gap-3 p-3 md:items-center md:gap-5 md:p-4',
    'gl-card gl-hot grid-cols-[56px_minmax(0,1fr)] md:grid-cols-[96px_minmax(0,1fr)_200px_220px]' => $exact,
    'grid-cols-[56px_minmax(0,1fr)] md:grid-cols-[80px_minmax(0,1fr)_170px_200px]' => ! $exact,
]) }}>
    <a href="{{ $url }}" tabindex="-1" aria-hidden="true">
        <x-ui.product-image
            :product="$product"
            conversion="thumb"
            plate
            :zone="$zone"
            ratio="gl-prow__plate"
            icon-class="size-6.5 md:size-8"
        />
    </a>

    <div class="flex min-w-0 flex-col gap-1.5">
        @if ($exact)
            <span class="gl-badge">
                <i aria-hidden="true"></i>
                <span class="md:hidden">{{ __('shop.search.exact_short') }}</span>
                <span class="max-md:hidden">{{ __('shop.search.exact') }}</span>
            </span>
        @endif

        <p class="flex flex-wrap items-baseline gap-x-2.5 text-sm">
            @if ($product->brand)
                <span class="font-bold text-steel-500">{{ $product->brand->name }}</span>
            @endif
            @if ($product->sku)
                <x-ui.data :label="__('shop.product.sku')">{{ $product->sku }}</x-ui.data>
            @endif
        </p>

        {{-- Точное совпадение стоит выше заголовка списка, поэтому оно само — заголовок второго уровня. --}}
        <{{ $exact ? 'h2' : 'h3' }} @class(['gl-prow__name font-extrabold text-white', 'text-md md:text-title' => $exact, 'text-base md:text-lg' => ! $exact])>
            <a href="{{ $url }}">{{ $product->name }}</a>
        </{{ $exact ? 'h2' : 'h3' }}>

        @if ($codes !== [])
            <x-ui.data class="max-md:hidden">{{ implode(' · ', $codes) }}</x-ui.data>
        @endif

        <x-ui.availability :availability="$product->availability" class="max-md:hidden" />
        <x-ui.availability :availability="$product->availability" variant="text" class="md:hidden" />

        <div class="flex items-center gap-2 pt-0.5 md:hidden">
            <span class="gl-price gl-price--row">
                {{ $price === null ? __('shop.price.on_request') : \App\Support\Typography::money($price->amount) }}
            </span>
            @if ($price === null)
                <x-lead.request-price :product="$product" class="ml-auto whitespace-nowrap" />
            @else
                <x-cart.add :product="$product" :variant="$exact ? 'primary' : 'secondary'" class="ml-auto" button-class="whitespace-nowrap gl-btn--calm" />
            @endif
        </div>
    </div>

    <x-ui.price :price="$price" class="items-end text-right max-md:hidden" />

    <div class="flex flex-col gap-2 max-md:hidden">
        @if ($price === null)
            <x-lead.request-price :product="$product" class="w-full" />
        @else
            <x-cart.add :product="$product" button-class="w-full gl-btn--calm" />
        @endif

        @if ($exact)
            <x-ui.button variant="neutral" :href="$url" class="w-full">{{ __('shop.search.open_product') }}</x-ui.button>
        @endif

        <x-compare.toggle :product="$product" />
        <x-favorites.toggle :product="$product" class="-mt-2" />
    </div>
</article>
