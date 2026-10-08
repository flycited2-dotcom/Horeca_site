{{--
    Мгновенная выдача под полем поиска (App\Livewire\InstantSearch; облик «Свечение»): «Товары»
    строками с ценой и наличием, «Категории» чипами, внизу — все результаты. Выдача — тёмное
    стекло, почти непрозрачное, чтобы текст читался на любой ауре сцены. В шапке она открывается
    под таблеткой и прижата к её правому краю. Стрелки вниз и вверх переходят по строкам, Esc
    и нажатие мимо закрывают выдачу.
--}}
@php
    use App\Support\Typography;

    $searchUrl = route('search', ['q' => $text]);
    $inHeader = $variant === 'header';
@endphp

<div
    {{ $attributes->class(['gl-sfw' => $inHeader, 'relative' => ! $inHeader]) }}
    x-data="{ open: false }"
    x-on:click.outside="open = false"
    x-on:keydown.escape="open = false"
    x-on:keydown.down.prevent="open && $focus.within($refs.results ?? $el).wrap().next()"
    x-on:keydown.up.prevent="open && $focus.within($refs.results ?? $el).wrap().previous()"
>
    <x-layout.search-form
        :id="$fieldId"
        :variant="$inHeader ? 'header' : 'footer'"
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
            class="gl-sheet gl-results"
        >
            @if ($result->isEmpty())
                <p class="gl-res__empty">{{ __('shop.search.empty_heading', ['query' => $text]) }}</p>
            @else
                @if ($result->layoutSwitched)
                    <p class="gl-res__empty gl-res__empty--note">{{ __('shop.search.switched', ['query' => $result->query]) }}</p>
                @endif

                @if ($result->products->isNotEmpty())
                    <p class="gl-res__h">{{ __('shop.search.products') }}</p>

                    <ul>
                        @foreach ($result->products as $product)
                            @php($price = $prices[$product->id] ?? null)
                            <li wire:key="instant-{{ $product->id }}">
                                <a href="{{ route('product', $product) }}" class="gl-res__item">
                                    <x-ui.product-image :product="$product" conversion="thumb" ratio="h-10 w-12 rounded-[10px]" icon-class="size-4.5" />
                                    <span class="min-w-0 text-base leading-[1.3] font-semibold text-white">{{ $product->name }}</span>
                                    <x-ui.availability :availability="$product->availability" variant="text" class="max-md:hidden" />
                                    <span class="text-base font-bold whitespace-nowrap text-white tabular">
                                        {{ $price === null ? __('shop.price.on_request') : Typography::money($price->amount) }}
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($result->categories->isNotEmpty())
                    <p class="gl-res__h">{{ __('shop.search.categories') }}</p>

                    <ul class="gl-res__cats">
                        @foreach ($result->categories as $category)
                            <li>
                                <a href="{{ route('category', $category) }}" class="gl-navchip tap-target">{{ $category->name }} · {{ Typography::number($category->products_count) }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <a href="{{ $searchUrl }}" class="gl-res__all">{{ trans_choice('shop.search.show_all', $result->total ?? 0, ['count' => Typography::number($result->total ?? 0), 'query' => $text]) }}</a>
            @endif
        </div>
    @endif
</div>
