@props(['name', 'url', 'image' => null, 'icon' => null, 'compact' => false, 'eager' => false, 'zone' => null, 'featured' => false])

{{--
    Плитка раздела (ТЗ §9, облик «Свечение», макет — экраны 2 и 4): стеклянная карточка со светящейся рамкой
    зоны раздела. Картинка группы товаров лежит сверху на светлой плашке, название и подписи из слота — под ней.
    Свечение даёт зона раздела (App\Support\CategoryZone по пиктограмме и названию): холодильные разделы —
    голубое, тепловые — оранжевое, остальные — серое. $zone задаёт её явно и выводится в data-zone.
    $featured — витринная плитка (App\View\HomeCatalog): плашка крупнее, метка «Холод» или «Жар» и название
    шрифтом заголовков. Бывает только у окрашенной плитки.
    На странице раздела подразделы идут компактными плитками. На телефоне плитка в две колонки,
    поэтому название и поля у неё мельче: длинное слово не рвётся посередине.
--}}
@php
    use App\Support\CategoryZone;

    $glow = $zone ?? CategoryZone::of($icon, $name);
    $featured = $featured && in_array($zone, [CategoryZone::COLD, CategoryZone::HOT], true);
@endphp

<a
    href="{{ $url }}"
    @if ($zone) data-zone="{{ $zone }}" @endif
    @if ($featured) data-featured @endif
    {{ $attributes->class([
        'group gl-card gl-ctile',
        'gl-cold' => $glow === CategoryZone::COLD,
        'gl-hot' => $glow === CategoryZone::HOT,
        'gl-neutral' => $glow === CategoryZone::NEUTRAL,
        'gl-ctile--compact' => $compact,
        'gl-ctile--featured' => $featured,
    ]) }}
>
    <x-catalog.category-picture
        :image="$image"
        :icon="$icon"
        :eager="$eager"
        plate
        ratio="gl-ctile__plate"
        :icon-class="$compact ? 'size-8' : ($featured ? 'size-16' : 'size-11')"
    />

    <span class="gl-ctile__body">
        @if ($featured)
            <span class="gl-badge"><i aria-hidden="true"></i>{{ __("shop.home.zones.{$zone}") }}</span>
        @endif
        <span class="gl-ctile__name">{{ $name }}</span>
        {{ $slot }}
    </span>
</a>
