@props(['filters', 'sorts', 'urlFor', 'variant' => 'segments'])

{{--
    Сортировка (макет, экраны 2 и 14): на широком экране — сегменты, чтобы «Сначала
    в наличии» было видно без открывания; на узком — выпадающий список. Сегменты — ссылки,
    список — GET-форма с кнопкой для браузера без скриптов; Livewire перехватывает оба.
--}}
@if ($variant === 'segments')
    <nav aria-label="{{ __('shop.catalog.sort.label') }}" {{ $attributes }}>
        <ul class="flex overflow-hidden rounded-control border border-line bg-surface">
            @foreach ($sorts as $sort)
                <li @class(['border-l border-line' => ! $loop->first])>
                    <a
                        href="{{ $urlFor($filters->withSort($sort)) }}"
                        wire:click.prevent="sortBy('{{ $sort->value }}')"
                        @if ($filters->sort === $sort) aria-current="true" @endif
                        @class([
                            'flex h-control items-center px-3.5 text-sm leading-none font-medium whitespace-nowrap transition-colors duration-150 ease-out focus-visible:-outline-offset-2',
                            'bg-slate' => $filters->sort === $sort,
                            'hover:bg-bg' => $filters->sort !== $sort,
                        ])
                    >{{ $sort->label() }}</a>
                </li>
            @endforeach
        </ul>
    </nav>
@else
    <form method="get" {{ $attributes }}>
        @foreach (Arr::except($filters->toQuery(), ['sort']) as $name => $value)
            <x-catalog.hidden-input :name="$name" :value="$value" />
        @endforeach

        <label for="listing-sort" class="sr-only">{{ __('shop.catalog.sort.label') }}</label>
        <select
            id="listing-sort"
            name="sort"
            wire:model.live="sort"
            class="h-control w-full rounded-control border border-line bg-surface px-3 text-base font-medium"
        >
            @foreach ($sorts as $sort)
                <option value="{{ $sort->value }}" @selected($filters->sort === $sort)>{{ $sort->label() }}</option>
            @endforeach
        </select>

        <noscript><x-ui.button type="submit" variant="neutral" class="mt-2 w-full">{{ __('shop.catalog.sort.label') }}</x-ui.button></noscript>
    </form>
@endif
