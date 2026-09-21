@props(['chips', 'resetUrl'])

{{--
    Применённые фильтры чипами над листингом (ТЗ §8.2, макет — экраны 2 и 14): крестик
    снимает один, «Сбросить всё» — все. На телефоне ряд прокручивается вбок. Ссылки работают
    без скриптов, Livewire снимает фильтр на месте.
--}}
@if ($chips !== [])
    <div {{ $attributes->class('flex items-center gap-2 max-md:-mx-3 max-md:overflow-x-auto max-md:px-3 max-md:py-1.5 max-md:[scrollbar-width:none] md:flex-wrap') }}>
        <span class="text-sm text-steel-500 max-md:hidden">{{ __('shop.catalog.selected') }}</span>

        @foreach ($chips as $chip)
            <x-ui.chip
                :href="$chip['url']"
                wire:key="chip-{{ $chip['filter'] }}-{{ $chip['brand'] }}"
                wire:click.prevent="removeFilter('{{ $chip['filter'] }}', '{{ $chip['brand'] }}')"
            >{{ $chip['label'] }}</x-ui.chip>
        @endforeach

        <a
            href="{{ $resetUrl }}"
            wire:click.prevent="resetFilters"
            class="tap-target ml-1 shrink-0 text-sm font-medium whitespace-nowrap text-accent-ink transition-colors duration-150 ease-out hover:text-accent-dark"
        >{{ __('shop.catalog.reset_all') }}</a>
    </div>
@endif
