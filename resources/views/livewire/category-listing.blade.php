{{--
    Листинг категории (App\Livewire\CategoryListing; макет — экраны 2, 10 и 14): заголовок
    со счётчиком, сортировка и вид, подразделы, «Подбор», чипы применённых фильтров,
    плитка или список, «Показать ещё» и страницы.
--}}
@php
    use App\Support\Typography;

    $models = fn (int $count): string => trans_choice('shop.catalog.models', $count, ['count' => Typography::number($count)]);
@endphp

<div class="flex flex-col gap-5">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="flex min-w-0 flex-col gap-1.5">
            <h1 class="text-xl font-bold md:text-2xl">{{ $title }}</h1>
            <p class="text-base text-steel-500 tabular" aria-live="polite">
                @if ($filters->isFiltered())
                    {{ trans_choice('shop.catalog.models_of', $categoryTotal, ['found' => Typography::number($slice->total), 'total' => Typography::number($categoryTotal)]) }}
                @else
                    {{ $models($slice->total) }} · {{ __('shop.catalog.in_stock_count', ['count' => Typography::number($inStockCount)]) }}
                @endif
            </p>
        </div>

        <div class="flex items-center gap-3 max-md:hidden">
            <x-catalog.sort-control :filters="$filters" :sorts="$sorts" :url-for="$urlFor" class="max-lg:hidden" />
            <x-catalog.view-toggle :view="$view" :filters="$filters" :url-for="$urlFor" />
        </div>
    </div>

    <x-catalog.subcategories :categories="$subcategories" />

    <x-catalog.filter-bar :filters="$filters" :sorts="$sorts" :url-for="$urlFor" />

    <div class="flex flex-col gap-6 xl:flex-row xl:items-start">
        <div class="max-xl:contents xl:sticky xl:top-21 xl:w-72 xl:shrink-0">
            <x-catalog.filter-panel
                id="catalog-filters"
                :filters="$filters"
                :brands="$brandOptions"
                :characteristics="$attributeFacets"
                :price-range="$priceRange"
                :in-stock-count="$inStockCount"
                :total="$slice->total"
                :action="$baseUrl"
                :reset-url="$urlFor($filters->cleared())"
                :brand-query="$brandQuery"
                :all-brands="$allBrands"
            />
        </div>

        <div class="flex min-w-0 flex-1 flex-col gap-5" wire:loading.delay.long.class="busy">
            <x-catalog.filter-chips :chips="$chips" :reset-url="$urlFor($filters->cleared())" />

            @if ($slice->total === 0)
                @if ($filters->isFiltered())
                    <x-catalog.empty-results :suggestions="$suggestions" :reset-url="$urlFor($filters->cleared())" />
                @else
                    <p class="rounded-card border border-line bg-surface p-6 text-steel-500">{{ __('shop.catalog.empty') }}</p>
                @endif
            @else
                <x-catalog.product-grid :products="$slice->products" :prices="$prices" :view="$view" />

                <x-catalog.pagination :slice="$slice" :filters="$filters" :url-for="$urlFor" class="pt-1" />
            @endif
        </div>
    </div>
</div>
