@props(['suggestions', 'resetUrl'])

{{--
    Фильтры ничего не нашли (макет, экран 2): не пустой экран, а объяснение и действие —
    какой фильтр снять и сколько позиций это даст, или сбросить все. Слаги брендов
    состоят из латиницы, цифр и дефисов (CatalogFilters), поэтому их можно вставить в вызов.
--}}
<div {{ $attributes->class('flex flex-col gap-6 rounded-card border border-line bg-surface p-6 md:flex-row md:gap-8 md:p-10') }}>
    <span class="flex size-16 shrink-0 items-center justify-center rounded-card border border-line bg-bg" aria-hidden="true">
        <svg class="size-8 text-steel-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
            <path d="M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14M16 16l4 4M8.5 11h5"/>
        </svg>
    </span>

    <div class="flex max-w-[60ch] flex-col gap-3.5">
        <h2 class="text-xl font-semibold">{{ __('shop.catalog.empty_filtered_heading') }}</h2>
        <p class="text-md">{{ __('shop.catalog.empty_filtered_text') }}</p>

        <div class="flex flex-wrap gap-2">
            @foreach ($suggestions as $suggestion)
                <x-ui.button
                    variant="secondary"
                    :href="$suggestion['url']"
                    wire:click.prevent="removeFilter('{{ $suggestion['filter'] }}', '{{ $suggestion['key'] }}')"
                >{{ trans_choice('shop.catalog.drop_filter', $suggestion['count'], ['filter' => $suggestion['label'], 'count' => \App\Support\Typography::number($suggestion['count'])]) }}</x-ui.button>
            @endforeach

            <x-ui.button variant="neutral" :href="$resetUrl" wire:click.prevent="resetFilters">{{ __('shop.catalog.reset_filters') }}</x-ui.button>
        </div>
    </div>
</div>
