@props(['filters', 'sorts', 'urlFor', 'panel' => 'catalog-filters', 'labels' => [], 'hidden' => []])

{{--
    Липкая полоса листинга ниже 1280 px (макет, экраны 10 и 14): «Фильтры · N» открывает
    шторку фильтров $panel, рядом — сортировка списком ниже 1024 px. С 1280 px фильтры
    стоят колонкой, и полоса не нужна.
--}}
<div {{ $attributes->class('sticky top-0 z-20 -mx-3 flex gap-2 border-y border-line-soft bg-bg px-3 py-2.5 md:top-17 md:-mx-6 md:px-6 lg:-mx-8 lg:px-8 xl:hidden') }}>
    <button
        type="button"
        popovertarget="{{ $panel }}"
        class="inline-flex h-control flex-1 items-center justify-center gap-2 rounded-control border border-accent bg-surface px-5 text-base leading-none font-medium text-accent-ink transition-colors duration-150 ease-out hover:bg-accent-soft md:flex-none"
    >
        {{ $filters->isFiltered() ? __('shop.catalog.filters_count', ['count' => $filters->activeCount()]) : __('shop.catalog.filters') }}
    </button>

    <x-catalog.sort-control variant="select" :filters="$filters" :sorts="$sorts" :url-for="$urlFor" :labels="$labels" :hidden="$hidden" class="flex-1 lg:hidden" />
</div>
