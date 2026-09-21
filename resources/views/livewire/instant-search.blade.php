{{--
    Мгновенная выдача под полем поиска (App\Livewire\InstantSearch; макет — экран 5):
    «Товары» строками с ценой и наличием, «Категории» чипами, внизу — все результаты.
    Стрелки вниз и вверх переходят по строкам, Esc и нажатие мимо закрывают выдачу.
--}}
@php
    use App\Support\Typography;

    $searchUrl = route('search', ['q' => $text]);
@endphp

<div
    {{ $attributes->class('relative') }}
    x-data="{ open: false }"
    x-on:click.outside="open = false"
    x-on:keydown.escape="open = false"
    x-on:keydown.down.prevent="open && $focus.within($refs.results ?? $el).wrap().next()"
    x-on:keydown.up.prevent="open && $focus.within($refs.results ?? $el).wrap().previous()"
>
    <x-layout.search-form
        :id="$fieldId"
        :placeholder="$variant === 'sku' ? __('shop.search.sku_placeholder') : null"
        live
    />

    <p id="{{ $fieldId }}-status" class="sr-only" aria-live="polite">
        @if ($result !== null)
            {{ trans_choice('shop.search.found_count', $result->total ?? 0, ['count' => $result->total ?? 0]) }}
        @endif
    </p>

    @if ($result !== null)
        <div
            x-ref="results"
            x-show="open"
            class="absolute inset-x-0 top-full z-40 mt-2 overflow-hidden rounded-control border border-line bg-surface shadow-raised"
        >
            @if ($result->isEmpty())
                <p class="px-3.5 py-3 text-base text-steel-500">{{ __('shop.search.empty_heading', ['query' => $text]) }}</p>
            @else
                @if ($result->layoutSwitched)
                    <p class="border-b border-line-soft px-3.5 py-2.5 text-sm text-steel-500">{{ __('shop.search.switched', ['query' => $result->query]) }}</p>
                @endif

                @if ($result->products->isNotEmpty())
                    <p class="border-b border-line-soft px-3.5 py-2.5 text-sm font-medium text-steel-500">{{ __('shop.search.products') }}</p>

                    <ul>
                        @foreach ($result->products as $product)
                            @php($price = $prices[$product->id] ?? null)
                            <li wire:key="instant-{{ $product->id }}">
                                <a
                                    href="{{ route('product', $product) }}"
                                    class="grid grid-cols-[40px_minmax(0,1fr)_auto] items-center gap-3.5 border-b border-line-soft px-3.5 py-2.5 transition-colors duration-150 ease-out hover:bg-bg focus-visible:-outline-offset-2 md:grid-cols-[40px_minmax(0,1fr)_auto_auto]"
                                >
                                    <x-ui.product-image :product="$product" conversion="thumb" ratio="h-8 w-10 rounded-xs" icon-class="size-4.5" />
                                    <span class="min-w-0 text-base leading-[1.3] font-medium">{{ $product->name }}</span>
                                    <x-ui.availability :availability="$product->availability" variant="text" class="max-md:hidden" />
                                    <span class="text-base font-semibold whitespace-nowrap tabular">
                                        {{ $price === null ? __('shop.price.on_request') : Typography::money($price->amount) }}
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($result->categories->isNotEmpty())
                    <p class="border-b border-line-soft px-3.5 py-2.5 text-sm font-medium text-steel-500">{{ __('shop.search.categories') }}</p>

                    <ul class="flex flex-wrap gap-2 px-3.5 py-3">
                        @foreach ($result->categories as $category)
                            <li>
                                <a
                                    href="{{ route('category', $category) }}"
                                    class="tap-target inline-flex items-center rounded-control border border-line px-2.5 py-1.5 text-sm leading-[1.3] font-medium transition-colors duration-150 ease-out hover:border-accent-ink hover:text-accent-ink"
                                >{{ $category->name }} · {{ Typography::number($category->products_count) }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <a
                    href="{{ $searchUrl }}"
                    class="block border-t border-line-soft bg-bg px-3.5 py-2.5 text-base font-medium text-accent-ink transition-colors duration-150 ease-out hover:text-accent-dark focus-visible:-outline-offset-2"
                >{{ trans_choice('shop.search.show_all', $result->total ?? 0, ['count' => Typography::number($result->total ?? 0), 'query' => $text]) }}</a>
            @endif
        </div>
    @endif
</div>
