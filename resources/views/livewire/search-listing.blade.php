{{--
    Страница поиска (App\Livewire\SearchListing; макет — экран 8): заголовок со счётчиками
    и «Уточнить:», точное совпадение по артикулу, «Сузить поиск», чипы, список строк,
    «Показать ещё» и страницы.
--}}
@php
    use App\Services\Catalog\CatalogFilters;
    use App\Support\Typography;

    $results = fn (int $count): string => trans_choice('shop.search.results', $count, ['count' => Typography::number($count)]);
@endphp

<div class="flex flex-col gap-5">
    @if ($tooShort)
        <h1 class="text-xl font-bold md:text-2xl">{{ __('shop.search.title') }}</h1>
        <p class="text-steel-500">{{ __('shop.search.short') }}</p>
    @elseif ($total === 0)
        <x-catalog.search-empty :query="$this->query" :popular="$popular" />
    @else
        @php
            $hidden = ['q' => $this->query, 'category' => $this->category];
            $sortLabels = ['popular' => __('shop.search.sort_relevance')];
            $inStockUrl = $urlFor(CatalogFilters::fromQuery($filters->toQuery() + ['in_stock' => '1']));
            $offerInStock = ! $filters->inStockOnly && $totalInStock > 0;
        @endphp

        <div class="flex flex-col gap-2.5">
            <h1 class="text-xl font-bold md:text-2xl">{{ __('shop.search.heading', ['query' => $this->query]) }}</h1>

            <p class="text-base text-steel-500 tabular" aria-live="polite">
                {{ $results($total) }} · {{ __('shop.catalog.in_stock_count', ['count' => Typography::number($totalInStock)]) }} · {{ trans_choice('shop.search.categories_count', $categoriesCount, ['count' => $categoriesCount]) }}
            </p>

            @if ($scope->layoutSwitched)
                <p class="text-base text-steel-500">{{ __('shop.search.switched', ['query' => $scope->query->text]) }}</p>
            @endif

            @if ($refine->count() > 1 || $offerInStock)
                <x-catalog.section-links
                    :label="__('shop.search.refine_label')"
                    :title="__('shop.search.refine')"
                    :sections="$refine->count() > 1 ? $refine : collect()"
                    :current="$section"
                    :filters="$filters"
                    :url-for="$urlFor"
                    class="pt-0.5"
                >
                    @if ($offerInStock)
                        <a
                            href="{{ $inStockUrl }}"
                            wire:click.prevent="$set('inStock', true)"
                            class="tap-target inline-flex h-8 shrink-0 items-center rounded-control border border-line bg-surface px-2.5 text-sm font-medium whitespace-nowrap transition-colors duration-150 ease-out hover:border-accent-ink hover:text-accent-ink"
                        >{{ __('shop.catalog.in_stock_only') }} · {{ Typography::number($totalInStock) }}</a>
                    @endif
                </x-catalog.section-links>
            @endif
        </div>

        @if ($exact)
            <x-catalog.product-row :product="$exact" :price="$prices[$exact->id] ?? null" exact wire:key="exact-{{ $exact->id }}" />
        @endif

        {{-- Кроме точного совпадения ничего не нашлось и фильтров нет: сужать нечего. --}}
        @if ($slice->total > 0 || $filters->isFiltered() || $section)
        <x-catalog.filter-bar :filters="$filters" :sorts="$sorts" :url-for="$urlFor" :labels="$sortLabels" :hidden="$hidden" />

        <div class="flex flex-col gap-6 xl:flex-row xl:items-start">
            <div class="max-xl:contents xl:sticky xl:top-21 xl:w-72 xl:shrink-0">
                <x-catalog.filter-panel
                    id="catalog-filters"
                    :title="__('shop.search.narrow')"
                    :filters="$filters"
                    :brands="$brandOptions"
                    :price-range="$priceRange"
                    :in-stock-count="$inStockCount"
                    :total="$slice->total"
                    :action="route('search')"
                    :hidden="$hidden"
                    :reset-url="$urlFor($filters->cleared(), ['category' => ''])"
                    :brand-query="$brandQuery"
                    :all-brands="$allBrands"
                />
            </div>

            <div class="flex min-w-0 flex-1 flex-col gap-4" wire:loading.delay.class="busy">
                <x-catalog.filter-chips :chips="$chips" :reset-url="$urlFor($filters->cleared(), ['category' => ''])" />

                @if ($slice->total === 0)
                    @if ($filters->isFiltered() || $section)
                        <x-catalog.empty-results :suggestions="$suggestions" :reset-url="$urlFor($filters->cleared(), ['category' => ''])" />
                    @endif
                @else
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <h2 class="text-base font-medium">
                            {{ $exact
                                ? trans_choice('shop.search.similar', $slice->total, ['count' => Typography::number($slice->total)])
                                : $results($slice->total) }}
                        </h2>

                        <x-catalog.sort-control :filters="$filters" :sorts="$sorts" :url-for="$urlFor" :labels="$sortLabels" class="max-lg:hidden" />
                    </div>

                    <div class="flex flex-col gap-2 md:gap-0 md:overflow-hidden md:rounded-card md:border md:border-line md:bg-surface md:[&>article:last-child]:border-b-0">
                        @foreach ($slice->products as $product)
                            <x-catalog.product-row wire:key="row-{{ $product->id }}" :product="$product" :price="$prices[$product->id] ?? null" />
                        @endforeach
                    </div>

                    <x-catalog.pagination :slice="$slice" :filters="$filters" :url-for="$urlFor" class="pt-1" />
                @endif
            </div>
        </div>
        @endif
    @endif
</div>
