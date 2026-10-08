@props(['category'])

{{--
    Подраздел в ленте над листингом (облик «Свечение»): круглый стеклянный чип с названием и числом
    товаров моноширинным; свечение — цвет зоны подраздела. Высота 36 px, цель нажатия — 44.
--}}
@php
    use App\Support\CategoryZone;

    $zone = CategoryZone::of($category['icon'] ?? null, $category['name']);
@endphp

<a
    href="{{ route('category', $category['slug']) }}"
    @class([
        'gl-lchip tap-target',
        'gl-cold' => $zone === CategoryZone::COLD,
        'gl-hot' => $zone === CategoryZone::HOT,
    ])
>
    {{ $category['name'] }}
    <span class="gl-lchip__n">{{ \App\Support\Typography::number($category['products_count']) }}</span>
</a>
