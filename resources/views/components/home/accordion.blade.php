@props(['shelf'])

{{--
    «В наличии» гармошкой (облик «Свечение», макет — блок acc): со 901 px модели стоят колонками, раскрыта одна —
    первая; остальные раскрываются при наведении и фокусе, без скриптов и автолистания. Колонка — это сама
    карточка: ссылка на товар, наличие и «В корзину» лежат в её панели и достижимы с клавиатуры и экранного
    диктора и в свёрнутом виде (панель только прозрачна), фокус внутри раскрывает карточку.
    Вертикальная подпись колонки скрыта от диктора: то же самое сказано в панели. На телефоне гармошки нет —
    карточки идут обычным вертикальным списком, панель видна у каждой.
    --gl-n — число колонок: от него зависит ширина раскрытой панели (resources/css/glow-home.css).
--}}
@php
    use App\Support\CategoryZone;
    use App\Support\Typography;
@endphp

<ul class="gl-acc" style="--gl-n: {{ count($shelf->items) }}">
    @foreach ($shelf->items as $item)
        <li @class([
            'gl-card gl-acc__item',
            'gl-cold' => $item['zone'] === CategoryZone::COLD,
            'gl-hot' => $item['zone'] === CategoryZone::HOT,
            'gl-is-open' => $loop->first,
        ])>
            <div class="gl-acc__tab" aria-hidden="true">
                <span class="gl-acc__num">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                <span class="gl-acc__mid">
                    <span class="gl-acc__vname">@if ($item['brand'])<small>{{ $item['brand'] }}</small> @endif{{ $item['name'] }}</span>
                </span>
                <span @class(['gl-acc__vprice', 'gl-acc__vprice--ask' => $item['price'] === null])>{{ $item['price'] === null ? __('shop.home.stock.on_request') : Typography::money($item['price']->amount) }}</span>
            </div>

            <div class="gl-acc__panel">
                <x-home.plate :image="$item['image']" :icon="$item['icon']" />

                <div class="gl-acc__head">
                    <div class="gl-acc__chips">
                        @if ($item['brand'])
                            <span class="gl-tag">{{ $item['brand'] }}</span>
                        @endif
                        <x-home.stock :availability="$item['availability']" />
                    </div>
                    <h3 class="gl-acc__name">
                        <a href="{{ route('product', $item['product']) }}">@if ($item['lead'])<span class="gl-mono">{{ $item['lead'] }}</span> @endif{{ $item['rest'] }}</a>
                    </h3>
                </div>

                <div class="gl-acc__buy">
                    <x-home.price :price="$item['price']" />
                    <x-home.buy :item="$item" />
                </div>
            </div>
        </li>
    @endforeach
</ul>
