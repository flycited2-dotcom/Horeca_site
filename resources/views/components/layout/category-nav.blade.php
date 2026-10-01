@props(['shell'])

{{--
    Ряд корневых категорий на десктопе (макет, экран 5): «Каталог» и главные разделы плашками
    с контуром — видно, что это кнопки, — не больше двух строк. Остальные разделы и те главные,
    что не поместились, лежат под «Ещё»: скрипт витрины оставляет там только их, а без скриптов
    «Ещё» показывает все. Главные разделы отмечает менеджер (категория → «Главный раздел»).
--}}
@php
    // Без отметок «Главный раздел» плашками идут первые восемь, остальные — под «Ещё».
    $plate = 'tap-target inline-flex h-9 items-center rounded-control border px-3.5 text-sm leading-none font-medium whitespace-nowrap transition-colors duration-150 ease-out';
    $idle = 'border-line bg-surface hover:border-accent-ink hover:text-accent-ink';
    $current = 'border-accent bg-accent-soft text-accent-ink';
    $catalog = 'gap-2 border-accent-ink bg-accent-ink font-semibold text-white hover:border-accent-dark hover:bg-accent-dark';
    $featured = $shell->featuredCategories();
    $others = $shell->otherCategories();
@endphp

<nav aria-label="{{ __('shop.layout.sections') }}" data-priority-nav {{ $attributes->class('border-b border-line bg-bg') }}>
    <div class="container-page flex items-start gap-2">
        <a
            href="{{ route('catalog') }}"
            @if ($shell->inCatalog) aria-current="page" @endif
            class="mt-1.25 shrink-0 {{ $plate }} {{ $catalog }}"
        >
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
                <path d="M4 7h16M4 12h16M4 17h16"/>
            </svg>
            {{ __('shop.layout.catalog') }}
        </a>

        <ul class="flex h-22 min-w-0 flex-1 flex-wrap content-start overflow-hidden">
            @foreach ($featured as $category)
                <li data-priority-item class="flex h-11 items-center pr-2">
                    <a
                        href="{{ route('category', $category['slug']) }}"
                        @if ($category['id'] === $shell->currentRootId) aria-current="true" @endif
                        class="{{ $plate }} {{ $category['id'] === $shell->currentRootId ? $current : $idle }}"
                    >{{ $category['name'] }}</a>
                </li>
            @endforeach
        </ul>

        @if ($shell->categories !== [])
            <details data-dismissable data-priority-more @if ($others !== []) data-priority-always @endif class="group relative mt-1.25 shrink-0">
                <summary class="{{ $plate }} {{ $idle }} cursor-pointer list-none gap-1 [&::-webkit-details-marker]:hidden">
                    {{ __('shop.layout.more') }}
                    <svg class="size-4 transition-transform duration-150 ease-out group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="m6 9 6 6 6-6"/>
                    </svg>
                </summary>

                <ul class="absolute top-full right-0 z-30 mt-2 grid w-max max-w-[min(40rem,calc(100vw-4rem))] grid-cols-2 gap-x-4 rounded-control border border-line bg-surface p-2 shadow-raised">
                    @foreach ($others as $category)
                        <li>
                            <a
                                href="{{ route('category', $category['slug']) }}"
                                class="flex min-h-control items-center justify-between gap-4 rounded-control px-3 text-base transition-colors duration-150 ease-out hover:bg-bg hover:text-accent-ink"
                            >
                                <span>{{ $category['name'] }}</span>
                                <span class="font-mono text-sm text-steel-500">{{ \App\Support\Typography::number($category['products_count']) }}</span>
                            </a>
                        </li>
                    @endforeach

                    @foreach ($featured as $category)
                        <li data-priority-extra>
                            <a
                                href="{{ route('category', $category['slug']) }}"
                                class="flex min-h-control items-center justify-between gap-4 rounded-control px-3 text-base transition-colors duration-150 ease-out hover:bg-bg hover:text-accent-ink"
                            >
                                <span>{{ $category['name'] }}</span>
                                <span class="font-mono text-sm text-steel-500">{{ \App\Support\Typography::number($category['products_count']) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif
    </div>
</nav>
