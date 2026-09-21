@props(['product', 'price' => null, 'stocks', 'facts'])

{{--
    Панель покупки (ТЗ §8.3, §6.5, макет — экран 3): цена, счётчик и кнопка по статусу,
    склады со сроком доставки (количество штук не показывается никогда), гарантия
    «Проверьте перед монтажом» и «К сравнению» (§8.5). На десктопе — липкая колонка 396 справа, ниже 1024 —
    под галереей; при прокрутке мимо неё появляется липкая полоса снизу.
--}}
@php
    use App\Enums\Availability;

    $install = $facts->installCheck();
@endphp

<div data-buy-panel {{ $attributes->class('flex flex-col gap-4 rounded-card border border-line bg-surface p-4 shadow-raised md:p-5') }}>
    <x-ui.price :price="$price" size="page" />

    @if ($product->availability === Availability::Discontinued)
        <p class="text-base text-steel-500">{{ __('shop.product.discontinued_note') }}</p>
        <x-ui.button variant="secondary" class="w-full">{{ __('shop.product.find_analog') }}</x-ui.button>
    @elseif ($price === null)
        <x-ui.button variant="secondary" class="w-full">{{ __('shop.product.request_price') }}</x-ui.button>
    @else
        <div class="flex gap-2">
            <x-ui.counter name="quantity" id="buy-quantity" :label="__('shop.counter.label').', '.$product->unit" />
            <x-ui.button class="flex-1">{{ __('shop.product.add_to_cart') }}</x-ui.button>
        </div>

        @if ($product->availability === Availability::OnOrder)
            <p class="text-sm text-steel-500">{{ __('shop.product.on_order_note') }}</p>
            <x-ui.button variant="neutral" class="w-full">{{ __('shop.product.ask_term') }}</x-ui.button>
        @endif
    @endif

    @if ($stocks->isNotEmpty() || $product->warranty_months)
        <ul class="flex flex-col border-t border-line-soft">
            @if ($stocks->isNotEmpty())
                <li class="grid grid-cols-[24px_minmax(0,1fr)] gap-2.5 border-b border-line-soft py-2.5">
                    <svg class="size-6 text-steel-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
                        <path d="M3 7h11v8H3zM14 10h4l3 3v2h-7M6.5 18a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3M17.5 18a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3"/>
                    </svg>
                    <div class="flex flex-col gap-1.5">
                        <span class="text-base font-semibold">{{ __('shop.product.warehouses') }}</span>
                        <ul class="flex flex-col gap-1 text-base">
                            @foreach ($stocks as $stock)
                                <li class="flex flex-wrap items-baseline justify-between gap-x-3">
                                    <span>{{ $stock->warehouse->name }}</span>
                                    <span class="text-sm text-steel-500">
                                        {{ $stock->status->getLabel() }}@if ($stock->warehouse->delivery_days_min && $stock->warehouse->delivery_days_max), <span class="tabular">{{ __('shop.product.delivery_days', ['min' => $stock->warehouse->delivery_days_min, 'max' => $stock->warehouse->delivery_days_max]) }}</span>@endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </li>
            @endif

            @if ($product->warranty_months)
                <li class="grid grid-cols-[24px_minmax(0,1fr)] gap-2.5 py-2.5">
                    <svg class="size-6 text-steel-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
                        <path d="M12 3l7 3v6c0 4-3 7-7 9-4-2-7-5-7-9V6z"/>
                    </svg>
                    <span class="text-base">
                        <span class="font-semibold">{{ trans_choice('shop.product.warranty', $product->warranty_months, ['count' => $product->warranty_months]) }}</span><br>
                        <span class="text-steel-500">{{ __('shop.product.warranty_note') }}</span>
                    </span>
                </li>
            @endif
        </ul>
    @endif

    @if ($install !== [])
        <div class="flex flex-col gap-1.5 rounded-card border border-line-soft bg-bg p-3">
            <span class="text-base font-semibold">{{ __('shop.product.install_check') }}</span>
            <dl class="flex flex-col gap-1">
                @foreach ($install as $row)
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] gap-2 text-sm">
                        <dt class="text-steel-500">{{ $row['label'] }}</dt>
                        <dd @class(['font-medium', 'font-mono' => $row['mono']])>{{ $row['value'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @endif

    <x-compare.toggle :product="$product" variant="button" />
</div>
