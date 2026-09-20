<x-layouts.app :title="__('shop.search.title')">
    @if ($tooShort)
        <h1 class="text-2xl font-bold md:text-3xl">{{ __('shop.search.title') }}</h1>
        <p class="mt-4 text-steel-500">{{ __('shop.search.short') }}</p>
    @elseif ($result->products->total() === 0)
        <h1 class="text-2xl font-bold md:text-3xl">{{ __('shop.search.empty_heading', ['query' => $query]) }}</h1>
        <p class="mt-4 max-w-prose text-base text-steel-500">{{ __('shop.search.empty_hint') }}</p>
        <div class="mt-6">
            <x-ui.button variant="neutral" :href="route('catalog')">{{ __('shop.layout.catalog') }}</x-ui.button>
        </div>
    @else
        <h1 class="text-2xl font-bold md:text-3xl">{{ __('shop.search.heading', ['query' => $query]) }}</h1>

        @if ($result->layoutSwitched)
            <p class="mt-2 text-base text-steel-500">{{ __('shop.search.switched', ['query' => $result->query]) }}</p>
        @endif

        <div class="mt-6">
            <x-catalog.toolbar :filters="$filters" :sorts="$sorts" :total="$result->products->total()" />

            <div class="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                @foreach ($result->products as $product)
                    <x-catalog.product-card :product="$product" :price="$prices[$product->id] ?? null" />
                @endforeach
            </div>

            <div class="mt-6">{{ $result->products->withQueryString()->links() }}</div>
        </div>
    @endif
</x-layouts.app>
