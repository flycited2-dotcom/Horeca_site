{{--
    Заявки клиента (ТЗ §11; макет — экран 7): все заявки по 20 на странице, новые сверху,
    с оплатой и отгрузкой отдельными колонками. Фильтры, поиск и выгрузка XLSX из макета —
    часть дилерского портала после запуска (ТЗ §21).
--}}
@php
    use App\Support\Typography;

    $pageLink = 'inline-flex h-control items-center rounded-control border border-line bg-surface px-4 text-base font-medium transition-colors duration-150 ease-out hover:border-accent-ink';
@endphp

<x-layouts.app :title="__('shop.account.orders.title')" noindex>
    <x-account.frame :user="$user" :company="$company" active="orders" :current="__('shop.account.orders.title')">
        <section class="lg:rounded-card lg:border lg:border-line lg:bg-surface" aria-label="{{ __('shop.account.orders.title') }}">
            @if ($orders->isEmpty())
                <div class="flex flex-col items-start gap-4 rounded-card border border-line bg-surface px-4 py-6 lg:rounded-none lg:border-0">
                    <p class="max-w-prose text-base text-steel-500">{{ __('shop.account.orders.empty') }}</p>
                    <x-ui.button :href="route('catalog')">{{ __('shop.account.summary.to_catalog') }}</x-ui.button>
                </div>
            @else
                <x-account.orders-table :orders="$orders" />

                <div class="flex flex-wrap items-center justify-between gap-4 pt-3 lg:border-t lg:border-line-soft lg:px-4 lg:py-3.5">
                    <p class="text-sm text-steel-500 tabular">{{ __('shop.account.orders.shown', ['from' => Typography::number((int) $orders->firstItem()), 'to' => Typography::number((int) $orders->lastItem()), 'total' => Typography::number($orders->total())]) }}</p>

                    @if ($orders->hasPages())
                        <nav aria-label="{{ __('shop.account.orders.pages') }}" class="flex gap-2">
                            @if ($orders->previousPageUrl())
                                <a href="{{ $orders->previousPageUrl() }}" rel="prev" class="{{ $pageLink }}">{{ __('shop.account.orders.previous') }}</a>
                            @endif
                            @if ($orders->nextPageUrl())
                                <a href="{{ $orders->nextPageUrl() }}" rel="next" class="{{ $pageLink }}">{{ __('shop.account.orders.next') }}</a>
                            @endif
                        </nav>
                    @endif
                </div>
            @endif
        </section>
    </x-account.frame>
</x-layouts.app>
