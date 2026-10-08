@props(['name', 'url', 'image' => null, 'icon' => null, 'compact' => false, 'eager' => false, 'zone' => null, 'featured' => false])

{{--
    Плитка раздела (ТЗ §9, макет — экраны 2 и 4): картинка группы товаров сверху, название
    и подписи из слота под ней. Плитка плоская с границей 1 px, тень — только при наведении.
    $zone (App\Support\CategoryZone, облик «Холод и жар») красит плитку главной: холодильные
    разделы — голубым, тепловые — оранжевым; белый фон фото растворяется в цвете плитки.
    $featured — витринная плитка главной (App\View\HomeCatalog): фото на всю высоту ячейки,
    метка «Холод» или «Жар» и название шрифтом заголовков. Бывает только у окрашенной плитки.
    На странице раздела подразделы идут компактными плитками. На телефоне плитка в две
    колонки, поэтому название и поля у неё мельче: длинное слово не рвётся посередине.
--}}
@php
    use App\Support\CategoryZone;

    $tint = match ($zone) {
        CategoryZone::COLD => ['tile' => 'border-cold-line bg-cold-bg', 'name' => 'text-cold-ink', 'picture' => 'bg-cold-bg', 'mark' => 'border-cold-line text-cold-ink'],
        CategoryZone::HOT => ['tile' => 'border-hot-line bg-hot-bg', 'name' => 'text-hot-ink', 'picture' => 'bg-hot-bg', 'mark' => 'border-hot-line text-hot-ink'],
        default => null,
    };
    $featured = $featured && $tint !== null;
@endphp

<a
    href="{{ $url }}"
    @if ($zone) data-zone="{{ $zone }}" @endif
    @if ($featured) data-featured @endif
    {{ $attributes->class([
        'group flex h-full flex-col overflow-hidden rounded-card border transition-[border-color,box-shadow] duration-150 ease-out hover:border-accent-ink hover:shadow-raised',
        $tint['tile'] ?? 'border-line bg-surface',
    ]) }}
>
    <x-catalog.category-picture
        :image="$image"
        :icon="$icon"
        :eager="$eager"
        :tint="$tint['picture'] ?? null"
        :ratio="$featured ? 'aspect-[16/10] lg:aspect-auto lg:min-h-0 lg:flex-1' : 'aspect-[4/3]'"
        :inset="$featured ? 'p-4 md:p-6' : 'p-3'"
        :icon-class="$compact ? 'size-8' : ($featured ? 'size-16' : 'size-11')"
        :class="$compact ? 'border-b border-line-soft max-md:p-2' : ($tint ? '' : 'border-b border-line-soft')"
    />

    <span @class([
        'flex flex-col',
        'flex-1 gap-1 p-3 md:p-3.5' => ! $compact && ! $featured,
        'flex-1 gap-1 p-3' => $compact,
        'gap-1.5 p-4 md:p-5' => $featured,
    ])>
        @if ($featured)
            <span class="self-start rounded-full border bg-surface px-2.5 py-0.5 text-sm font-semibold {{ $tint['mark'] }}">{{ __("shop.home.zones.{$zone}") }}</span>
        @endif
        <span @class([
            'leading-tight font-semibold hyphens-auto wrap-break-word',
            'text-base md:text-md' => ! $compact && ! $featured,
            'text-base' => $compact,
            'font-display text-lg font-bold text-balance md:text-xl lg:text-2xl' => $featured,
            $tint['name'] ?? 'text-ink',
        ])>{{ $name }}</span>
        {{ $slot }}
    </span>
</a>
