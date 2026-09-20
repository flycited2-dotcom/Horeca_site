<x-layouts.app :title="$category->meta_title ?: $category->name" :description="$category->meta_description">
    <x-catalog.breadcrumbs :category="$category" />

    <h1 class="mt-3 text-2xl font-bold md:text-3xl">{{ $category->h1 ?: $category->name }}</h1>

    @if ($children->isNotEmpty())
        <nav class="mt-4 flex flex-wrap gap-2" aria-label="{{ __('shop.catalog.subcategories') }}">
            @foreach ($children as $child)
                <a href="{{ route('category', $child) }}"
                   class="inline-flex h-control items-center rounded-full border border-line bg-surface px-4 text-base transition-colors duration-150 ease-out hover:border-accent-ink">
                    {{ $child->name }}
                    <span class="ml-2 text-sm tabular text-steel-500">{{ number_format($child->products_count, 0, ',', "\u{00A0}") }}</span>
                </a>
            @endforeach
        </nav>
    @endif

    <div class="mt-6 flex flex-col gap-6 lg:flex-row">
        <x-catalog.filters :filters="$filters" :brands="$brands" :price-range="$priceRange" :action="route('category', $category)" />

        <div class="min-w-0 flex-1">
            <x-catalog.toolbar :filters="$filters" :sorts="$sorts" :total="$products->total()" />

            @if ($products->isEmpty())
                <p class="mt-6 rounded-card border border-line bg-surface p-6 text-steel-500">{{ __('shop.catalog.empty') }}</p>
            @else
                <div class="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                    @foreach ($products as $product)
                        <x-catalog.product-card :product="$product" :price="$prices[$product->id] ?? null" />
                    @endforeach
                </div>

                <div class="mt-6">{{ $products->withQueryString()->links() }}</div>
            @endif
        </div>
    </div>

    @if ($category->seo_text)
        <section class="mt-10 max-w-prose text-base text-steel-500">{!! nl2br(e($category->seo_text)) !!}</section>
    @endif
</x-layouts.app>
