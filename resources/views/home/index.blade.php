<x-layouts.app :title="__('shop.home.title')">
    <h1 class="font-heading text-3xl font-semibold">{{ __('shop.home.heading') }}</h1>

    <section class="mt-8" aria-labelledby="catalog-heading">
        <h2 id="catalog-heading" class="font-heading text-xl font-semibold">{{ __('shop.home.catalog') }}</h2>

        @if ($categories->isEmpty())
            <p class="mt-4 text-steel-600">{{ __('shop.home.catalog_empty') }}</p>
        @else
            <ul class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 md:gap-4 lg:grid-cols-4 lg:gap-6">
                @foreach ($categories as $category)
                    <li class="rounded-card border border-steel-200 bg-surface p-4">
                        <p class="font-heading text-lg font-semibold">{{ $category->name }}</p>
                        <p class="mt-1 text-sm tabular-nums text-steel-600">
                            {{ trans_choice('shop.home.products_count', $category->products_count, ['count' => number_format($category->products_count, 0, ',', "\u{00A0}")]) }}
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-layouts.app>
