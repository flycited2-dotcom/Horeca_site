@props(['tile', 'image' => null])

{{--
    Карточка раздела главной (облик «Свечение», макет — блок cat): метка зоны («Холод», «Жар» или «Раздел»),
    фото на светлой плашке со свечением зоны, название, число позиций и сколько из них в наличии, «Открыть раздел».
    Две витринные карточки (холод и жар, App\View\HomeCatalog) крупные и называют ещё и подразделы, остальные —
    обычные. Раздел без фото — линованная плашка с одним словом из названия вместо серой пиктограммы.
    Вся карточка — одна ссылка, как у плитки раздела на других страницах.
--}}
@php
    use App\Support\CategoryZone;
    use App\Support\Typography;

    $zone = $tile['zone'];
    $featured = $tile['featured'];
    $kids = $featured && $tile['children'] !== [];

    $mark = match ($zone) {
        CategoryZone::COLD => __('shop.home.zones.cold'),
        CategoryZone::HOT => __('shop.home.zones.hot'),
        default => __('shop.home.section_mark'),
    };
@endphp

<a
    href="{{ route('category', $tile['slug']) }}"
    data-zone="{{ $zone }}"
    @if ($featured) data-featured @endif
    {{ $attributes->class([
        'gl-card gl-cat',
        'gl-cat--lg' => $featured,
        'gl-cat--sm' => ! $featured,
        'gl-cold' => $zone === CategoryZone::COLD,
        'gl-hot' => $zone === CategoryZone::HOT,
        'gl-cat--photo' => (bool) $image,
        'gl-cat--type' => ! $image,
    ]) }}
>
    <span class="gl-badge"><i aria-hidden="true"></i>{{ $mark }}</span>

    <span class="gl-cat__photo" aria-hidden="true">
        @if ($image)
            <x-home.plate :image="$image" />
        @else
            <span class="gl-type"><span>{{ $tile['label'] }}</span></span>
        @endif
    </span>

    <h3 class="gl-cat__title">{{ $tile['name'] }}</h3>

    {{-- «0 в наличии» звучит как «ничего нет»: без товаров на складах — только число позиций. --}}
    <p @class(['gl-cat__meta', 'gl-cat__meta--kids' => $kids])><span>{{ trans_choice('shop.home.positions', $tile['products_count'], ['count' => Typography::number($tile['products_count'])]) }}</span>@if ($tile['in_stock'] > 0) · <span>{{ __('shop.home.in_stock_count', ['count' => Typography::number($tile['in_stock'])]) }}</span>@endif</p>

    @if ($kids)
        <p class="gl-cat__kids">{{ implode(', ', $tile['children']) }}</p>
    @endif

    <span class="gl-cat__foot">
        <span class="gl-cat__go">{{ __('shop.home.open_section') }}</span>
        <span class="gl-round"><x-home.icon name="arrow" /></span>
    </span>
</a>
