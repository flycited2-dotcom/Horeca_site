@props([
    'id',
    'filters',
    'brands',
    'priceRange' => null,
    'inStockCount' => 0,
    'total' => 0,
    'action',
    'resetUrl',
    'brandQuery' => '',
    'allBrands' => false,
    'title' => null,
    'hidden' => [],
])

{{--
    Панель «Подбор» (ТЗ §8.2, макет — экраны 2, 10 и 14). С 1280 px — липкая колонка,
    фильтр применяется сразу, счётчики брендов и наличия считаются при остальных фильтрах.
    Ниже 1280 — шторка на popover (утилита filter-sheet) с кнопкой «Показать N моделей».
    Без скриптов это обычная GET-форма: те же имена полей, что в адресе ($hidden — запрос
    и раздел на странице поиска), и все бренды;
    со скриптами бренды после шестого свёрнуты в «Ещё N брендов», поиск по ним — на сервере.
--}}
@php
    $shownBrands = \App\Livewire\CategoryListing::BRANDS_SHOWN;
    $selectedBrands = count($filters->brands);
@endphp

<div id="{{ $id }}" popover class="filter-sheet" aria-labelledby="{{ $id }}-title">
    <form method="get" action="{{ $action }}" wire:submit="applyFilters" class="flex min-h-0 flex-1 flex-col xl:gap-3">
        <input type="hidden" name="sort" value="{{ $filters->sort->value }}">
        @foreach (array_filter($hidden, fn ($value) => $value !== '' && $value !== null) as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach

        <div class="flex min-h-0 flex-1 flex-col overflow-y-auto bg-surface xl:overflow-visible xl:rounded-card xl:border xl:border-line">
            <div class="flex items-center justify-between gap-3 border-b border-line-soft px-4 py-2 xl:py-3.5">
                <h2 id="{{ $id }}-title" class="text-lg font-semibold">
                    <span class="xl:hidden">{{ __('shop.catalog.filters') }}</span>
                    <span class="max-xl:hidden">{{ $title ?? __('shop.catalog.filters_title') }}</span>
                </h2>

                <div class="flex items-center gap-2">
                    @if ($filters->isFiltered())
                        <a href="{{ $resetUrl }}" wire:click.prevent="resetFilters" class="tap-target text-sm text-accent-ink transition-colors duration-150 ease-out hover:text-accent-dark">{{ __('shop.catalog.filters_reset') }}</a>
                    @endif

                    <button
                        type="button"
                        popovertarget="{{ $id }}"
                        popovertargetaction="hide"
                        class="flex size-control items-center justify-center rounded-control border border-line transition-colors duration-150 ease-out hover:border-accent-ink xl:hidden"
                    >
                        <span class="sr-only">{{ __('shop.catalog.filters_close') }}</span>
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
                            <path d="M6 6l12 12M18 6 6 18"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="flex items-center justify-between gap-3 border-b border-line-soft px-4 py-2">
                <x-ui.toggle name="in_stock" id="{{ $id }}-in-stock" :checked="$filters->inStockOnly" wire:model.live="inStock">
                    {{ __('shop.catalog.in_stock_only') }}
                </x-ui.toggle>
                <span class="font-mono text-sm text-steel-500">{{ \App\Support\Typography::number($inStockCount) }}</span>
            </div>

            <div role="group" aria-labelledby="{{ $id }}-price" class="flex flex-col gap-3 border-b border-line-soft p-4">
                <span id="{{ $id }}-price" class="text-base font-semibold">{{ __('shop.catalog.price') }}</span>

                <div class="flex items-center gap-2">
                    <label for="{{ $id }}-price-from" class="sr-only">{{ __('shop.catalog.price_from') }}</label>
                    <input
                        id="{{ $id }}-price-from"
                        type="text"
                        name="price_from"
                        inputmode="numeric"
                        autocomplete="off"
                        value="{{ $filters->priceFrom }}"
                        placeholder="{{ $priceRange ? \App\Support\Typography::number(intdiv($priceRange['min']->kopecks, 100)) : '' }}"
                        wire:model.live.debounce.600ms="priceFrom"
                        class="h-control w-full min-w-0 rounded-control border border-line bg-surface px-3 text-base tabular placeholder:text-steel-500 focus:border-accent"
                    >
                    <span class="text-steel-500" aria-hidden="true">—</span>
                    <label for="{{ $id }}-price-to" class="sr-only">{{ __('shop.catalog.price_to') }}</label>
                    <input
                        id="{{ $id }}-price-to"
                        type="text"
                        name="price_to"
                        inputmode="numeric"
                        autocomplete="off"
                        value="{{ $filters->priceTo }}"
                        placeholder="{{ $priceRange ? \App\Support\Typography::number(intdiv($priceRange['max']->kopecks, 100)) : '' }}"
                        wire:model.live.debounce.600ms="priceTo"
                        class="h-control w-full min-w-0 rounded-control border border-line bg-surface px-3 text-base tabular placeholder:text-steel-500 focus:border-accent"
                    >
                </div>

                @if ($priceRange)
                    <span class="text-sm text-steel-500">
                        {{ __('shop.catalog.price_range', ['min' => \App\Support\Typography::money($priceRange['min']), 'max' => \App\Support\Typography::money($priceRange['max'])]) }}
                    </span>
                @endif
            </div>

            @if ($brands->isNotEmpty())
                @php
                    $query = mb_strtolower(trim($brandQuery));
                    $listed = $query === '' ? $brands : $brands->filter(fn ($brand) => str_contains(mb_strtolower($brand->name), $query));
                    $hiddenCount = $query === '' && ! $allBrands
                        ? $brands->skip($shownBrands)->reject(fn ($brand) => in_array($brand->slug, $filters->brands, true))->count()
                        : 0;
                @endphp

                <div role="group" aria-labelledby="{{ $id }}-brand" class="flex flex-col gap-2 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <span id="{{ $id }}-brand" class="text-base font-semibold">{{ __('shop.catalog.brand') }}</span>
                        @if ($selectedBrands > 0)
                            <span class="font-mono text-sm text-steel-500">{{ __('shop.catalog.brands_selected', ['count' => $selectedBrands]) }}</span>
                        @endif
                    </div>

                    @if ($brands->count() > $shownBrands)
                        <div class="requires-js">
                            <label for="{{ $id }}-brand-search" class="sr-only">{{ __('shop.catalog.brand_search') }}</label>
                            <input
                                id="{{ $id }}-brand-search"
                                type="search"
                                autocomplete="off"
                                placeholder="{{ __('shop.catalog.brand_search') }}"
                                wire:model.live.debounce.300ms="brandQuery"
                                class="h-control w-full rounded-control border border-line bg-surface px-3 text-base placeholder:text-steel-500 focus:border-accent"
                            >
                        </div>
                    @endif

                    <div class="-mx-1 flex flex-col px-1 xl:max-h-80 xl:overflow-y-auto">
                        @foreach ($listed as $brand)
                            @php($selected = in_array($brand->slug, $filters->brands, true))
                            <label
                                wire:key="brand-{{ $brand->id }}"
                                @if ($hiddenCount > 0 && $loop->index >= $shownBrands && ! $selected) data-collapsible @endif
                                class="flex min-h-control cursor-pointer items-center gap-2.5 text-base"
                            >
                                <input
                                    type="checkbox"
                                    name="brand[]"
                                    value="{{ $brand->slug }}"
                                    @checked($selected)
                                    wire:model.live="brands"
                                    class="size-4.5 shrink-0 accent-accent"
                                >
                                <span class="min-w-0 flex-1 truncate">{{ $brand->name }}</span>
                                <span class="font-mono text-sm text-steel-500">{{ \App\Support\Typography::number($brand->products_count) }}</span>
                            </label>
                        @endforeach
                    </div>

                    @if ($hiddenCount > 0)
                        <button
                            type="button"
                            wire:click="$set('allBrands', true)"
                            class="requires-js tap-target self-start text-sm text-accent-ink transition-colors duration-150 ease-out hover:text-accent-dark"
                        >{{ trans_choice('shop.catalog.brands_more', $hiddenCount, ['count' => $hiddenCount]) }}</button>
                    @endif
                </div>
            @endif
        </div>

        <div class="border-t border-line-soft bg-bg p-3 xl:border-0 xl:bg-transparent xl:p-0">
            <x-ui.button
                type="submit"
                class="w-full"
                x-on:click="$el.closest('[popover]')?.matches(':popover-open') && $el.closest('[popover]').hidePopover()"
            >{{ trans_choice('shop.catalog.show_models', $total, ['count' => \App\Support\Typography::number($total)]) }}</x-ui.button>
        </div>
    </form>
</div>
