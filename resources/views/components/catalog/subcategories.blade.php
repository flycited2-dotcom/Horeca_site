@props(['categories'])

{{--
    Подразделы листинга (ТЗ §8.2); $categories — массивы name, slug, products_count:
    Livewire-листинг хранит их как простые данные. В корневых разделах подразделов бывает
    60+: на телефоне они идут одной строкой с прокруткой, с 768 px первые двенадцать видны
    сразу, остальные раскрывает «Ещё N разделов» — это <details>, работает без скриптов.
--}}
@php
    $shown = 12;
    $categories = collect($categories)->values();
@endphp

@if ($categories->isNotEmpty())
    <nav aria-label="{{ __('shop.catalog.subcategories') }}" {{ $attributes }}>
        <ul class="flex gap-2 max-md:-mx-3 max-md:overflow-x-auto max-md:px-3 max-md:py-1.5 max-md:[scrollbar-width:none] md:flex-wrap">
            @foreach ($categories as $child)
                <li @class(['shrink-0', 'md:hidden' => $loop->index >= $shown])><x-catalog.subcategory-link :category="$child" /></li>
            @endforeach
        </ul>

        @if ($categories->count() > $shown)
            <details class="group mt-2 max-md:hidden">
                <summary class="tap-target inline-flex cursor-pointer list-none items-center gap-1 text-sm font-medium text-accent-ink transition-colors duration-150 ease-out hover:text-accent-dark [&::-webkit-details-marker]:hidden">
                    {{ trans_choice('shop.catalog.subcategories_more', $categories->count() - $shown, ['count' => $categories->count() - $shown]) }}
                    <svg class="size-4 transition-transform duration-150 ease-out group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="m6 9 6 6 6-6"/>
                    </svg>
                </summary>

                <ul class="mt-2 flex flex-wrap gap-2">
                    @foreach ($categories->skip($shown) as $child)
                        <li><x-catalog.subcategory-link :category="$child" /></li>
                    @endforeach
                </ul>
            </details>
        @endif
    </nav>
@endif
