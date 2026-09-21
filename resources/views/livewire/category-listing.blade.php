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
                    {{ __('shop.catalog.models_of', ['found' => Typography::number($slice->total), 'total' => $models($categoryTotal)]) }}
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

    <div class="sticky top-0 z-20 -mx-3 flex gap-2 border-y border-line-soft bg-bg px-3 py-2.5 md:top-17 md:-mx-6 md:px-6 lg:-mx-8 lg:px-8 xl:hidden">
        <button
            type="button"
            popovertarget="catalog-filters"
            class="inline-flex h-control flex-1 items-center justify-center gap-2 rounded-control border border-accent bg-surface px-5 text-base leading-none font-medium text-accent-ink transition-colors duration-150 ease-out hover:bg-accent-soft md:flex-none"
        >
            {{ $filters->isFiltered() ? __('shop.catalog.filters_count', ['count' => $filters->activeCount()]) : __('shop.catalog.filters') }}
        </button>

        <x-catalog.sort-control variant="select" :filters="$filters" :sorts="$sorts" :url-for="$urlFor" class="flex-1 lg:hidden" />
    </div>

    <div class="flex flex-col gap-6 xl:flex-row xl:items-start">
        <div class="max-xl:contents xl:sticky xl:top-21 xl:w-72 xl:shrink-0">
            <x-catalog.filter-panel
                id="catalog-filters"
                :filters="$filters"
                :brands="$brandOptions"
                :price-range="$priceRange"
                :in-stock-count="$inStockCount"
                :total="$slice->total"
                :action="$baseUrl"
                :reset-url="$urlFor($filters->cleared())"
                :brand-query="$brandQuery"
                :all-brands="$allBrands"
            />
        </div>

        <div class="flex min-w-0 flex-1 flex-col gap-5" wire:loading.delay.class="busy">
            @if ($chips !== [])
                <div class="flex items-center gap-2 max-md:-mx-3 max-md:overflow-x-auto max-md:px-3 max-md:py-1.5 max-md:[scrollbar-width:none] md:flex-wrap">
                    <span class="text-sm text-steel-500 max-md:hidden">{{ __('shop.catalog.selected') }}</span>

                    @foreach ($chips as $chip)
                        <x-ui.chip
                            :href="$chip['url']"
                            wire:key="chip-{{ $chip['filter'] }}-{{ $chip['brand'] }}"
                            wire:click.prevent="removeFilter('{{ $chip['filter'] }}', '{{ $chip['brand'] }}')"
                        >{{ $chip['label'] }}</x-ui.chip>
                    @endforeach

                    <a
                        href="{{ $urlFor($filters->cleared()) }}"
                        wire:click.prevent="resetFilters"
                        class="tap-target ml-1 shrink-0 text-sm font-medium whitespace-nowrap text-accent-ink transition-colors duration-150 ease-out hover:text-accent-dark"
                    >{{ __('shop.catalog.reset_all') }}</a>
                </div>
            @endif

            @if ($slice->total === 0)
                @if ($filters->isFiltered())
                    <x-catalog.empty-results :suggestions="$suggestions" :reset-url="$urlFor($filters->cleared())" />
                @else
                    <p class="rounded-card border border-line bg-surface p-6 text-steel-500">{{ __('shop.catalog.empty') }}</p>
                @endif
            @else
                @if ($view === 'list')
                    <x-catalog.product-table :products="$slice->products" :prices="$prices" class="max-md:hidden" />
                @endif

                <div @class(['grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-6 lg:grid-cols-3', 'md:hidden' => $view === 'list'])>
                    @foreach ($slice->products as $product)
                        <x-catalog.product-card wire:key="card-{{ $product->id }}" :product="$product" :price="$prices[$product->id] ?? null" />
                    @endforeach
                </div>

                <x-catalog.pagination :slice="$slice" :filters="$filters" :url-for="$urlFor" class="pt-1" />
            @endif
        </div>
    </div>
</div>
