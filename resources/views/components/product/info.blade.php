@props(['product', 'facts', 'stocks', 'pickup' => null, 'pages'])

{{--
    Сведения о товаре (ТЗ §8.3, макет — экраны 3 и 14): «Характеристики / Описание /
    Доставка и оплата / Гарантия». С 768 px — вкладки, на телефоне — аккордеоны, без
    скриптов — все разделы подряд со своими заголовками. Пустой раздел не выводится.
    Скрытое со скриптами помечено data-inactive: класс js на <html> прячет его до первой
    отрисовки, переключает скрипт витрины.

    Характеристик мало (случай 4b) — таблица не растягивается на всю ширину.
--}}
@php
    $specs = $facts->specs();

    $panels = array_filter([
        'specs' => __('shop.product.tab_specs'),
        'description' => filled($product->description) ? __('shop.product.tab_description') : null,
        'delivery' => $pickup || $pages->has('dostavka') || $pages->has('oplata') || $stocks->isNotEmpty() ? __('shop.product.tab_delivery') : null,
        'warranty' => $product->warranty_months || $pages->has('garantiya') ? __('shop.product.tab_warranty') : null,
    ]);

    $first = array_key_first($panels);
@endphp

<div data-tabs {{ $attributes->class('rounded-card border border-line bg-surface') }}>
    <div role="tablist" aria-label="{{ __('shop.product.tabs') }}" class="requires-js hidden gap-1 border-b border-line-soft px-4 md:flex">
        @foreach ($panels as $key => $title)
            <button
                type="button"
                role="tab"
                id="tab-{{ $key }}"
                aria-controls="panel-{{ $key }}"
                aria-selected="{{ $key === $first ? 'true' : 'false' }}"
                data-tab-button="{{ $key }}"
                class="px-2.5 py-3.5 text-md leading-tight font-medium text-steel-500 transition-colors duration-150 ease-out hover:text-ink focus-visible:-outline-offset-2 aria-selected:font-semibold aria-selected:text-ink aria-selected:shadow-[inset_0_-2px_0_var(--color-accent)]"
            >{{ $title }}</button>
        @endforeach
    </div>

    @foreach ($panels as $key => $title)
        <section
            id="panel-{{ $key }}"
            data-panel="{{ $key }}"
            @if ($key !== $first) data-inactive @endif
            aria-labelledby="heading-{{ $key }}"
            class="border-b border-line-soft last:border-b-0 md:border-b-0"
        >
            <h2 id="heading-{{ $key }}" data-panel-heading class="text-lg font-semibold">
                <button
                    type="button"
                    data-panel-toggle
                    aria-expanded="{{ $key === $first ? 'true' : 'false' }}"
                    class="flex min-h-control w-full items-center justify-between gap-3 px-4 py-3 text-left focus-visible:-outline-offset-2"
                >
                    {{ $title }}
                    <svg class="size-5 shrink-0 text-steel-500 transition-transform duration-150 ease-out [[aria-expanded=true]>&]:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="m6 9 6 6 6-6"/>
                    </svg>
                </button>
            </h2>

            <div data-panel-body class="px-4 pb-5 md:pt-4">
                @if ($key === 'specs')
                    <dl @class(['flex flex-col', 'max-w-[420px]' => count($specs) <= 6])>
                        @foreach ($specs as $row)
                            <div class="grid grid-cols-2 gap-4 border-t border-line-soft py-2 text-base first:border-t-0">
                                <dt class="text-steel-500">{{ $row['label'] }}</dt>
                                <dd @class(['font-medium', 'font-mono text-sm' => $row['mono']])>{{ $row['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    @if (count($specs) <= 6)
                        <p class="mt-3 text-sm text-steel-500">{{ __('shop.product.specs_note') }}</p>
                    @endif
                @elseif ($key === 'description')
                    <div class="max-w-prose text-base whitespace-pre-line">{{ trim($product->description) }}</div>
                @elseif ($key === 'delivery')
                    <div class="flex max-w-prose flex-col gap-2 text-base">
                        @if ($stocks->isNotEmpty())
                            <p>{{ __('shop.product.delivery_from') }}</p>
                        @endif
                        @if ($pickup)
                            <p>{{ __('shop.product.pickup', ['address' => $pickup]) }}</p>
                        @endif
                        @foreach (['dostavka', 'oplata'] as $slug)
                            @if ($pages->has($slug))
                                <a href="{{ url($slug) }}" class="self-start font-medium text-accent-ink transition-colors duration-150 ease-out hover:text-accent-dark">{{ $pages[$slug]->title }}</a>
                            @endif
                        @endforeach
                    </div>
                @elseif ($key === 'warranty')
                    <div class="flex max-w-prose flex-col gap-2 text-base">
                        @if ($product->warranty_months)
                            <p><span class="font-semibold">{{ trans_choice('shop.product.warranty', $product->warranty_months, ['count' => $product->warranty_months]) }}</span>. {{ __('shop.product.warranty_note') }}</p>
                        @endif
                        @if ($pages->has('garantiya'))
                            <a href="{{ url('garantiya') }}" class="self-start font-medium text-accent-ink transition-colors duration-150 ease-out hover:text-accent-dark">{{ $pages['garantiya']->title }}</a>
                        @endif
                    </div>
                @endif
            </div>
        </section>
    @endforeach
</div>
