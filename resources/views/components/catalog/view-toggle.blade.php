@props(['view', 'urlFor', 'filters'])

{{--
    Вид листинга «плитка / список» (ТЗ §8.2, макет — экраны 2 и 10): выбор запоминается
    в cookie. Без скриптов это ссылки с ?view=, Livewire переключает на месте.
--}}
<nav aria-label="{{ __('shop.catalog.view') }}" {{ $attributes }}>
    <ul class="flex overflow-hidden rounded-control border border-line bg-surface">
        @foreach (['grid' => 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z', 'list' => 'M4 6h16M4 12h16M4 18h16'] as $mode => $path)
            <li @class(['border-l border-line' => ! $loop->first])>
                <a
                    href="{{ $urlFor($filters, ['view' => $mode]) }}"
                    wire:click.prevent="setView('{{ $mode }}')"
                    @if ($view === $mode) aria-current="true" @endif
                    @class([
                        'flex size-control items-center justify-center transition-colors duration-150 ease-out focus-visible:-outline-offset-2',
                        'bg-line-soft text-ink' => $view === $mode,
                        'text-steel-500 hover:bg-bg' => $view !== $mode,
                    ])
                >
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
                        <path d="{{ $path }}"/>
                    </svg>
                    <span class="sr-only">{{ __("shop.catalog.view_{$mode}") }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</nav>
