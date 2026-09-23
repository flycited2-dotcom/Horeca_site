{{--
    Подборка «Соберём кухню под задачу» (ТЗ §8.1): название, описание и карточки товаров,
    как в листинге. Пустая подборка говорит, куда идти дальше.
--}}
<x-layouts.app :meta="$meta">
    <x-catalog.breadcrumbs :current="$collection->name" />

    <div class="mt-3 flex flex-col gap-5">
        <div class="flex max-w-prose flex-col gap-1.5">
            <h1 class="text-xl font-bold md:text-2xl">{{ $collection->name }}</h1>
            @if (filled($collection->description))
                <p class="text-base text-steel-500">{{ $collection->description }}</p>
            @endif
            @if ($products->isNotEmpty())
                <p class="text-sm text-steel-500 tabular">{{ trans_choice('shop.catalog.models', $products->count(), ['count' => $products->count()]) }}</p>
            @endif
        </div>

        @if ($products->isEmpty())
            <div class="flex max-w-prose flex-col items-start gap-4 rounded-card border border-line bg-surface p-6">
                <p class="text-base text-steel-500">{{ __('shop.collections.empty') }}</p>
                <x-ui.button :href="route('catalog')">{{ __('shop.favorites.to_catalog') }}</x-ui.button>
            </div>
        @else
            <x-catalog.product-grid :products="$products" :prices="$prices" class="xl:grid-cols-4" />
        @endif
    </div>
</x-layouts.app>
