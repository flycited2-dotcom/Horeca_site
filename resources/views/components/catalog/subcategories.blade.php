@props(['categories'])

{{--
    Подразделы листинга (ТЗ §8.2, облик «Свечение»); $categories — массивы name, slug, products_count, icon,
    image, tile: Livewire-листинг хранит их как простые данные. С 768 px самые большие
    подразделы — плитки с картинкой группы товаров (App\Services\Catalog\CategoryImages),
    остальные раскрывает «Ещё N разделов» — это <details>, работает без скриптов. На телефоне
    плитки заняли бы экран до первого товара, поэтому там все подразделы идут одной строкой
    чипов с прокруткой.
--}}
@php
    $categories = collect($categories)->values();
    $tiles = $categories->filter(fn (array $category): bool => $category['tile'] ?? false);
    $rest = $categories->reject(fn (array $category): bool => $category['tile'] ?? false);
@endphp

@if ($categories->isNotEmpty())
    <nav aria-label="{{ __('shop.catalog.subcategories') }}" {{ $attributes }}>
        <ul class="flex gap-2 max-md:-mx-3 max-md:overflow-x-auto max-md:px-3 max-md:py-1.5 max-md:[scrollbar-width:none] md:hidden">
            @foreach ($categories as $child)
                <li class="shrink-0"><x-catalog.subcategory-link :category="$child" /></li>
            @endforeach
        </ul>

        <ul class="grid grid-cols-3 gap-3 max-md:hidden lg:grid-cols-4 xl:grid-cols-6">
            @foreach ($tiles as $child)
                <li>
                    <x-catalog.category-tile :name="$child['name']" :url="route('category', $child['slug'])" :image="$child['image'] ?? null" :icon="$child['icon'] ?? null" compact>
                        <span class="text-sm font-semibold text-steel-500 tabular">{{ trans_choice('shop.catalog.models', $child['products_count'], ['count' => \App\Support\Typography::number($child['products_count'])]) }}</span>
                    </x-catalog.category-tile>
                </li>
            @endforeach
        </ul>

        @if ($rest->isNotEmpty())
            <details class="group mt-2 max-md:hidden">
                <summary class="tap-target inline-flex min-h-control cursor-pointer list-none items-center gap-1 text-sm font-bold text-accent-ink transition-colors duration-150 ease-out hover:text-accent-dark [&::-webkit-details-marker]:hidden">
                    {{ trans_choice('shop.catalog.subcategories_more', $rest->count(), ['count' => $rest->count()]) }}
                    <svg class="size-4 transition-transform duration-150 ease-out group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="m6 9 6 6 6-6"/>
                    </svg>
                </summary>

                <ul class="mt-2 flex flex-wrap gap-2">
                    @foreach ($rest as $child)
                        <li><x-catalog.subcategory-link :category="$child" /></li>
                    @endforeach
                </ul>
            </details>
        @endif
    </nav>
@endif
