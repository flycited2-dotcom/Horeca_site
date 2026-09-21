@props(['shell'])

{{--
    Ряд корневых категорий на десктопе (макет, экран 5): «Каталог», разделы в одну строку
    и «Ещё». Что не поместилось, переносится на скрытую вторую строку; скрипт витрины
    оставляет в «Ещё» только эти разделы, а без скриптов «Ещё» показывает все.
--}}
<nav aria-label="{{ __('shop.layout.sections') }}" data-priority-nav {{ $attributes->class('border-b border-line bg-bg') }}>
    <div class="container-page flex h-12 items-stretch gap-1">
        <a
            href="{{ route('catalog') }}"
            @if ($shell->inCatalog) aria-current="page" @endif
            @class([
                'flex shrink-0 items-center gap-2 bg-slate px-3.5 text-base leading-none font-semibold transition-colors duration-150 ease-out hover:text-accent-ink',
                'shadow-[inset_0_-2px_0_var(--color-accent)]' => $shell->inCatalog,
            ])
        >
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
                <path d="M4 7h16M4 12h16M4 17h16"/>
            </svg>
            {{ __('shop.layout.catalog') }}
        </a>

        <ul class="flex h-12 min-w-0 flex-1 flex-wrap content-start items-stretch overflow-hidden">
            @foreach ($shell->categories as $category)
                <li data-priority-item class="flex h-12">
                    <a
                        href="{{ route('category', $category['slug']) }}"
                        @if ($category['id'] === $shell->currentRootId) aria-current="true" @endif
                        @class([
                            'flex items-center px-3 text-base leading-none font-medium whitespace-nowrap transition-colors duration-150 ease-out hover:text-accent-ink',
                            'shadow-[inset_0_-2px_0_var(--color-accent)]' => $category['id'] === $shell->currentRootId,
                        ])
                    >{{ $category['name'] }}</a>
                </li>
            @endforeach
        </ul>

        @if ($shell->categories !== [])
            <details data-dismissable data-priority-more class="group relative shrink-0">
                <summary class="flex h-12 cursor-pointer list-none items-center gap-1 px-3 text-base leading-none font-medium transition-colors duration-150 ease-out hover:text-accent-ink [&::-webkit-details-marker]:hidden">
                    {{ __('shop.layout.more') }}
                    <svg class="size-4 transition-transform duration-150 ease-out group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="m6 9 6 6 6-6"/>
                    </svg>
                </summary>

                <ul class="absolute top-full right-0 z-30 mt-px grid w-max max-w-[min(40rem,calc(100vw-4rem))] grid-cols-2 gap-x-4 rounded-control border border-line bg-surface p-2 shadow-raised">
                    @foreach ($shell->categories as $category)
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
