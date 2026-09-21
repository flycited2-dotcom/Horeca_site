<x-layouts.app :title="__('shop.home.title')">
    <section class="rounded-card bg-slate p-6 md:p-8">
        <h1 class="text-2xl font-bold md:text-3xl">{{ __('shop.home.heading') }}</h1>

        @if ($categories->isEmpty())
            <p class="mt-4 text-steel-500">{{ __('shop.home.catalog_empty') }}</p>
        @else
            <nav class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4" aria-label="{{ __('shop.home.catalog') }}">
                @foreach ($categories as $category)
                    <a href="{{ route('category', $category) }}"
                       class="flex items-center gap-3 rounded-card border border-slate-line bg-surface p-4 transition-shadow duration-150 ease-out hover:shadow-raised">
                        <x-ui.equipment-icon :icon="$category->icon" class="size-8 text-steel-400" />

                        <span class="min-w-0">
                            <span class="block text-lg font-semibold">{{ $category->name }}</span>
                            <span class="block text-sm tabular text-steel-500">
                                {{ trans_choice('shop.home.products_count', $category->products_count, ['count' => number_format($category->products_count, 0, ',', "\u{00A0}")]) }}
                            </span>
                        </span>
                    </a>
                @endforeach
            </nav>

            <div class="mt-6">
                <x-ui.button variant="neutral" :href="route('catalog')">{{ __('shop.layout.all_categories') }}</x-ui.button>
            </div>
        @endif
    </section>

    <section class="mt-6 flex flex-col gap-3 rounded-card border border-line bg-surface p-4 md:p-6" aria-labelledby="sku-heading">
        <h2 id="sku-heading" class="text-lg font-semibold">{{ __('shop.search.sku_heading') }}</h2>
        <p class="text-base text-steel-500">{{ __('shop.search.sku_text') }}</p>
        <livewire:instant-search field-id="sku-search" variant="sku" class="max-w-3xl" />
    </section>

    @if ($inStock->isNotEmpty())
        <section class="mt-10" aria-labelledby="in-stock-heading">
            <h2 id="in-stock-heading" class="text-lg font-semibold">{{ __('shop.home.in_stock_strip') }}</h2>

            <div class="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($inStock as $product)
                    <x-catalog.product-card :product="$product" :price="$prices[$product->id] ?? null" />
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.app>
