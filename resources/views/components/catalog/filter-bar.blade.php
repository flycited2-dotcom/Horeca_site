@props(['filters', 'sorts', 'urlFor', 'panel' => 'catalog-filters', 'labels' => [], 'hidden' => []])

{{--
    Липкая полоса листинга ниже 1280 px (облик «Свечение», макет — экраны 10 и 14): «Фильтры · N» —
    круглая стеклянная кнопка, открывает шторку фильтров $panel, рядом — сортировка списком ниже 1024 px.
    С 1280 px фильтры стоят колонкой, и полоса не нужна. Шире 820 px полоса встаёт под липкой шапкой-таблеткой.
--}}
<div {{ $attributes->class('gl-bar sticky z-20 -mx-3 flex gap-2 border-y px-3 py-2.5 md:-mx-6 md:px-6 lg:-mx-8 lg:px-8 xl:hidden') }}>
    <button
        type="button"
        popovertarget="{{ $panel }}"
        class="gl-btn gl-btn--glass gl-btn--sm flex-1 md:flex-none"
    >
        {{ $filters->isFiltered() ? __('shop.catalog.filters_count', ['count' => $filters->activeCount()]) : __('shop.catalog.filters') }}
    </button>

    <x-catalog.sort-control variant="select" :filters="$filters" :sorts="$sorts" :url-for="$urlFor" :labels="$labels" :hidden="$hidden" class="flex-1 lg:hidden" />
</div>
