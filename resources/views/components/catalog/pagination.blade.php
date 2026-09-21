@props(['slice', 'filters', 'urlFor'])

{{--
    Низ листинга (ТЗ §8.2, макет — экран 2): сколько показано и полоса прогресса,
    «Показать ещё 24» и номера страниц. «Показать ещё» дописывает следующую страницу
    на месте; без скриптов это ссылка на неё. Номера страниц — для поисковиков и для
    тех, кто хочет перейти сразу далеко.
--}}
@php
    $pageUrl = fn (int $page): string => $urlFor($filters, $page > 1 ? ['page' => $page] : []);
@endphp

<div {{ $attributes->class('flex flex-col items-center gap-4') }}>
    <p class="text-sm text-steel-500 tabular">
        @if ($slice->firstPage > 1)
            {{ __('shop.catalog.shown_range', ['from' => \App\Support\Typography::number($slice->from()), 'to' => \App\Support\Typography::number($slice->to()), 'total' => \App\Support\Typography::number($slice->total)]) }}
        @else
            {{ __('shop.catalog.shown', ['to' => \App\Support\Typography::number($slice->to()), 'total' => \App\Support\Typography::number($slice->total)]) }}
        @endif
    </p>

    <div class="h-1 w-full max-w-90 overflow-hidden rounded-full bg-line-soft" aria-hidden="true">
        <span class="block h-full rounded-full bg-accent" style="width: {{ $slice->progress() }}%"></span>
    </div>

    @if ($slice->hasMore())
        <x-ui.button
            variant="secondary"
            :href="$pageUrl($slice->lastPage + 1)"
            wire:click.prevent="loadMore"
            class="min-w-60 max-md:w-full"
        >{{ __('shop.catalog.load_more', ['count' => $slice->nextCount()]) }}</x-ui.button>
    @endif

    @if ($slice->pageCount() > 1)
        <nav aria-label="{{ __('shop.catalog.pages') }}">
            <ul class="flex flex-wrap items-center justify-center gap-1.5">
                @if ($slice->firstPage > 1)
                    <li>
                        <a href="{{ $pageUrl($slice->firstPage - 1) }}" wire:click.prevent="goToPage({{ $slice->firstPage - 1 }})" rel="prev"
                           class="flex h-control items-center rounded-control border border-line bg-surface px-3.5 text-base font-medium transition-colors duration-150 ease-out hover:border-accent-ink">{{ __('shop.catalog.prev') }}</a>
                    </li>
                @endif

                @foreach ($slice->pageLinks() as $page)
                    <li>
                        @if ($page === null)
                            <span class="flex size-control items-center justify-center text-base text-steel-500" aria-hidden="true">…</span>
                        @elseif ($slice->isOnScreen($page))
                            <span aria-current="page" class="flex size-control items-center justify-center rounded-control border border-accent-ink bg-accent-ink text-base font-medium text-white tabular">
                                <span class="sr-only">{{ __('shop.catalog.page', ['page' => $page]) }}</span><span aria-hidden="true">{{ $page }}</span>
                            </span>
                        @else
                            <a href="{{ $pageUrl($page) }}" wire:click.prevent="goToPage({{ $page }})"
                               class="flex size-control items-center justify-center rounded-control border border-line bg-surface text-base font-medium tabular transition-colors duration-150 ease-out hover:border-accent-ink">
                                <span class="sr-only">{{ __('shop.catalog.page', ['page' => $page]) }}</span><span aria-hidden="true">{{ $page }}</span>
                            </a>
                        @endif
                    </li>
                @endforeach

                @if ($slice->hasMore())
                    <li>
                        <a href="{{ $pageUrl($slice->lastPage + 1) }}" wire:click.prevent="goToPage({{ $slice->lastPage + 1 }})" rel="next"
                           class="flex h-control items-center rounded-control border border-line bg-surface px-3.5 text-base font-medium transition-colors duration-150 ease-out hover:border-accent-ink">{{ __('shop.catalog.next') }}</a>
                    </li>
                @endif
            </ul>
        </nav>
    @endif
</div>
