@props(['slice', 'filters', 'urlFor'])

{{--
    Низ листинга (ТЗ §8.2, облик «Свечение», макет — экран 2): сколько показано и полоса прогресса,
    «Показать ещё 24» и номера страниц — круглые кнопки, текущая — оранжевая. «Показать ещё» дописывает
    следующую страницу на месте; без скриптов это ссылка на неё. Номера страниц — для поисковиков и для
    тех, кто хочет перейти сразу далеко.
--}}
@php
    $pageUrl = fn (int $page): string => $urlFor($filters, $page > 1 ? ['page' => $page] : []);
@endphp

<div {{ $attributes->class('flex flex-col items-center gap-4') }}>
    <p class="text-sm font-semibold text-steel-500 tabular">
        @if ($slice->firstPage > 1)
            {{ __('shop.catalog.shown_range', ['from' => \App\Support\Typography::number($slice->from()), 'to' => \App\Support\Typography::number($slice->to()), 'total' => \App\Support\Typography::number($slice->total)]) }}
        @else
            {{ __('shop.catalog.shown', ['to' => \App\Support\Typography::number($slice->to()), 'total' => \App\Support\Typography::number($slice->total)]) }}
        @endif
    </p>

    <div class="gl-meter" aria-hidden="true">
        <span style="width: {{ $slice->progress() }}%"></span>
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
            <ul class="flex flex-wrap items-center justify-center gap-2">
                @if ($slice->firstPage > 1)
                    <li>
                        <a href="{{ $pageUrl($slice->firstPage - 1) }}" wire:click.prevent="goToPage({{ $slice->firstPage - 1 }})" rel="prev"
                           class="gl-pg">{{ __('shop.catalog.prev') }}</a>
                    </li>
                @endif

                @foreach ($slice->pageLinks() as $page)
                    <li>
                        @if ($page === null)
                            <span class="gl-pg gl-pg--num gl-pg--gap" aria-hidden="true">…</span>
                        @elseif ($slice->isOnScreen($page))
                            <span aria-current="page" class="gl-pg gl-pg--num gl-pg--on">
                                <span class="sr-only">{{ __('shop.catalog.page', ['page' => $page]) }}</span><span aria-hidden="true">{{ $page }}</span>
                            </span>
                        @else
                            <a href="{{ $pageUrl($page) }}" wire:click.prevent="goToPage({{ $page }})"
                               class="gl-pg gl-pg--num">
                                <span class="sr-only">{{ __('shop.catalog.page', ['page' => $page]) }}</span><span aria-hidden="true">{{ $page }}</span>
                            </a>
                        @endif
                    </li>
                @endforeach

                @if ($slice->hasMore())
                    <li>
                        <a href="{{ $pageUrl($slice->lastPage + 1) }}" wire:click.prevent="goToPage({{ $slice->lastPage + 1 }})" rel="next"
                           class="gl-pg">{{ __('shop.catalog.next') }}</a>
                    </li>
                @endif
            </ul>
        </nav>
    @endif
</div>
