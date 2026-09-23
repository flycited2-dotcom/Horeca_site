{{--
    Корзина (ТЗ §10.1, макет — экран 6): страница служебная, в поиск не попадает. После
    «Повторить заказ» (§11) над корзиной — позиции, которые купить уже нельзя, с причиной.
--}}
@php
    $repeat = session('repeat');
    $skipped = is_array($repeat) ? ($repeat['skipped'] ?? []) : [];
@endphp

<x-layouts.app :title="__('shop.cart.title')" noindex>
    <x-catalog.breadcrumbs :current="__('shop.cart.title')" />

    @if ($skipped !== [])
        <section class="mt-3 flex flex-col gap-3 rounded-card border border-incoming-line bg-incoming-bg p-4" aria-labelledby="repeat-skipped">
            <div class="flex flex-col gap-1">
                <h2 id="repeat-skipped" class="text-lg font-semibold text-incoming">{{ __('shop.account.repeat.skipped_heading', ['number' => $repeat['number']]) }}</h2>
                <p class="text-base">{{ __('shop.account.repeat.skipped_text') }}</p>
            </div>
            <ul class="flex flex-col divide-y divide-incoming-line/50">
                @foreach ($skipped as $line)
                    <li class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-2">
                        <span class="flex min-w-0 flex-col gap-0.5">
                            @if ($line['slug'] && $line['reason'] !== 'hidden')
                                <a href="{{ route('product', $line['slug']) }}" class="text-base font-semibold transition-colors duration-150 ease-out hover:text-accent-ink">{{ $line['name'] }}</a>
                            @else
                                <span class="text-base font-semibold">{{ $line['name'] }}</span>
                            @endif
                            @if ($line['sku'])
                                <x-ui.data :label="__('shop.account.order.sku')">{{ $line['sku'] }}</x-ui.data>
                            @endif
                        </span>
                        <span class="text-sm font-medium text-incoming">{{ __('shop.account.repeat.reasons.'.$line['reason']) }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <div class="mt-3">
        <livewire:cart-page />
    </div>
</x-layouts.app>
