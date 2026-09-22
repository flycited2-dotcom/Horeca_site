{{--
    Карточка товара (ТЗ §8.3, макет — экран 3, случаи 4a и 4b; телефон — экран 14).
    Порядок в разметке — галерея, покупка, сведения: на телефоне и планшете покупка идёт
    сразу под галереей, на десктопе встаёт липкой колонкой справа.
--}}
@php
    use App\Enums\Availability;
    use App\View\ProductFacts;

    $facts = new ProductFacts($product);
@endphp

<x-layouts.app :title="$product->meta_title ?: $product->name" :description="$product->meta_description">
    <x-catalog.breadcrumbs :product="$product" />

    <header class="mt-4 flex flex-col gap-2">
        <p class="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-base">
            @if ($product->brand?->is_active)
                <a href="{{ route('brand', $product->brand) }}" class="font-medium transition-colors duration-150 ease-out hover:text-accent-ink">{{ $product->brand->name }}</a>
            @elseif ($product->brand)
                <span class="font-medium">{{ $product->brand->name }}</span>
            @endif
            @if ($product->sku)
                <x-ui.data>{{ __('shop.product.sku') }} {{ $product->sku }}</x-ui.data>
            @endif
            <x-ui.availability :availability="$product->availability" />
        </p>

        <h1 class="max-w-[26ch] text-xl font-bold md:text-2xl">{{ $product->h1 ?: $product->name }}</h1>
    </header>

    <div class="mt-5 grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_396px] lg:gap-x-6">
        <x-product.gallery :product="$product" class="lg:col-start-1" />

        <aside class="lg:sticky lg:top-21 lg:col-start-2 lg:row-span-2 lg:row-start-1 lg:self-start">
            <x-product.buy-panel :product="$product" :price="$price" :stocks="$stocks" :facts="$facts" />
        </aside>

        <div class="flex min-w-0 flex-col gap-4 lg:col-start-1">
            <x-product.info :product="$product" :facts="$facts" :stocks="$stocks" :pickup="$pickup" :pages="$pages" />

            @if ($related->isNotEmpty())
                <section class="flex flex-col gap-3.5 rounded-card border border-line bg-surface p-4 md:p-5" aria-labelledby="related-heading">
                    <h2 id="related-heading" class="text-lg font-semibold">{{ __('shop.product.related') }}</h2>

                    <ul class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        @foreach ($related as $item)
                            @php($itemPrice = $prices[$item->id] ?? null)
                            <li class="flex flex-col gap-2 rounded-card border border-line p-3">
                                <a href="{{ route('product', $item) }}" class="text-base leading-snug font-medium transition-colors duration-150 ease-out hover:text-accent-ink">{{ $item->name }}</a>
                                <span class="mt-auto text-lg font-semibold tabular">
                                    {{ $itemPrice === null ? __('shop.price.on_request') : \App\Support\Typography::money($itemPrice->amount) }}
                                </span>
                                @if ($itemPrice === null)
                                    <x-lead.request-price :product="$item" class="w-full" />
                                @else
                                    <x-cart.add :product="$item" variant="secondary" button-class="w-full" />
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    </div>

    @if ($similar->isNotEmpty())
        <section class="mt-10" aria-labelledby="similar-heading">
            <h2 id="similar-heading" class="text-xl font-bold">{{ __('shop.product.similar') }}</h2>

            <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-6 lg:grid-cols-4">
                @foreach ($similar as $item)
                    <x-catalog.product-card :product="$item" :price="$prices[$item->id] ?? null" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- Окна коротких заявок (ТЗ §6.5): только те, что нужны этому товару. --}}
    @if ($product->availability === Availability::Discontinued)
        <x-lead.dialog id="lead-analog" type="analog_request" :product="$product" />
    @elseif ($price === null)
        <x-lead.dialog id="lead-price" type="price_request" :product="$product" />
    @else
        <x-lead.dialog id="lead-one-click" type="one_click" :product="$product" />
        @if ($product->availability === Availability::OnOrder)
            <x-lead.dialog id="lead-term" type="availability_request" :product="$product" />
        @endif
    @endif

    <script type="application/ld+json">{!! \App\Support\StructuredData::json($structuredData) !!}</script>

    @if ($product->availability !== Availability::Discontinued)
        <x-slot:bottom-bar>
            <x-product.sticky-bar :product="$product" :price="$price" />
        </x-slot:bottom-bar>
    @endif
</x-layouts.app>
