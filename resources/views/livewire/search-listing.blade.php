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
                <nav aria-label="{{ __('shop.search.refine_label') }}" class="flex items-center gap-2 pt-0.5 max-md:-mx-3 max-md:overflow-x-auto max-md:px-3 max-md:py-1.5 max-md:[scrollbar-width:none] md:flex-wrap">
                    <span class="shrink-0 text-sm text-steel-500">{{ __('shop.search.refine') }}</span>

                    @if ($refine->count() > 1)
                        @foreach ($refine as $category)
                            <a
                                href="{{ $urlFor($filters, ['category' => $category->slug]) }}"
                                wire:click.prevent="$set('category', '{{ $category->slug }}')"
                                wire:key="refine-{{ $category->id }}"
                                @if ($section?->id === $category->id) aria-current="true" @endif
                                @class([
                                    'tap-target inline-flex h-8 shrink-0 items-center rounded-control border px-2.5 text-sm font-medium whitespace-nowrap transition-colors duration-150 ease-out hover:border-accent-ink hover:text-accent-ink',
                                    'border-accent bg-accent-soft text-accent-ink' => $section?->id === $category->id,
                                    'border-line bg-surface' => $section?->id !== $category->id,
                                ])
                            >{{ $category->name }} · {{ Typography::number($category->products_count) }}</a>
                        @endforeach
                    @endif

                    @if ($offerInStock)
                        <a
                            href="{{ $inStockUrl }}"
                            wire:click.prevent="$set('inStock', true)"
                            class="tap-target inline-flex h-8 shrink-0 items-center rounded-control border border-line bg-surface px-2.5 text-sm font-medium whitespace-nowrap transition-colors duration-150 ease-out hover:border-accent-ink hover:text-accent-ink"
                        >{{ __('shop.catalog.in_stock_only') }} · {{ Typography::number($totalInStock) }}</a>
                    @endif
                </nav>
            @endif
        </div>

        @if ($exact)
            <x-catalog.product-row :product="$exact" :price="$prices[$exact->id] ?? null" exact wire:key="exact-{{ $exact->id }}" />
        @endif

        {{-- Кроме точного совпадения ничего не нашлось и фильтров нет: сужать нечего. --}}
        @if ($slice->total > 0 || $filters->isFiltered() || $section)
        <div class="sticky top-0 z-20 -mx-3 flex gap-2 border-y border-line-soft bg-bg px-3 py-2.5 md:top-17 md:-mx-6 md:px-6 lg:-mx-8 lg:px-8 xl:hidden">
            <button
                type="button"
                popovertarget="catalog-filters"
                class="inline-flex h-control flex-1 items-center justify-center gap-2 rounded-control border border-accent bg-surface px-5 text-base leading-none font-medium text-accent-ink transition-colors duration-150 ease-out hover:bg-accent-soft md:flex-none"
            >
                {{ $filters->isFiltered() ? __('shop.catalog.filters_count', ['count' => $filters->activeCount()]) : __('shop.catalog.filters') }}
            </button>

            <x-catalog.sort-control variant="select" :filters="$filters" :sorts="$sorts" :url-for="$urlFor" :labels="$sortLabels" :hidden="$hidden" class="flex-1 lg:hidden" />
        </div>

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
                            href="{{ $urlFor($filters->cleared(), ['category' => '']) }}"
                            wire:click.prevent="resetFilters"
                            class="tap-target ml-1 shrink-0 text-sm font-medium whitespace-nowrap text-accent-ink transition-colors duration-150 ease-out hover:text-accent-dark"
                        >{{ __('shop.catalog.reset_all') }}</a>
                    </div>
                @endif

                @if ($slice->total === 0)
                    @if ($filters->isFiltered() || $section)
                        <x-catalog.empty-results :suggestions="$suggestions" :reset-url="$urlFor($filters->cleared(), ['category' => ''])" />
                    @endif
                @else
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <p class="text-base font-medium">
                            {{ $exact
                                ? trans_choice('shop.search.similar', $slice->total, ['count' => Typography::number($slice->total)])
                                : $results($slice->total) }}
                        </p>

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
