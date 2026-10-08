@props(['item'])

{{--
    Карточка товара ленты главной (облик «Свечение», макет — блок ship): фото на светлой плашке с меткой наличия,
    бренд, название, цена и круглая кнопка корзины. Вся карточка открывает товар — это растянутая ссылка названия, кнопка
    корзины лежит над ней. Метка наличия стоит дважды: на фото (с 601 px) и в тексте (на телефоне, где фото мелкое);
    лишняя скрыта display:none и диктору не слышна.
--}}
@php
    use App\Support\CategoryZone;

    $zone = $item['zone'];
@endphp

<li {{ $attributes->class([
    'gl-card gl-ship',
    'gl-cold' => $zone === CategoryZone::COLD,
    'gl-hot' => $zone === CategoryZone::HOT,
    'gl-card--duo' => $zone === CategoryZone::NEUTRAL,
]) }}>
    <div class="gl-ship__ph">
        <x-home.plate :image="$item['image']" :icon="$item['icon']" />
        <x-home.stock :availability="$item['availability']" />
    </div>

    <div class="gl-ship__body">
        <x-home.stock :availability="$item['availability']" />
        @if ($item['brand'])
            <span class="gl-ship__brand">{{ $item['brand'] }}</span>
        @endif
        <h3 class="gl-ship__name">
            <a href="{{ route('product', $item['product']) }}">@if ($item['lead'])<span class="gl-mono">{{ $item['lead'] }}</span> @endif{{ $item['rest'] }}</a>
        </h3>
    </div>

    <div class="gl-ship__foot">
        <x-home.price :price="$item['price']" />
        <x-home.buy :item="$item" :round="true" />
    </div>
</li>
