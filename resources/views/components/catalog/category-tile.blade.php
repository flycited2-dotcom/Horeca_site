@props(['name', 'url', 'image' => null, 'icon' => null, 'compact' => false, 'eager' => false, 'zone' => null])

{{--
    Плитка раздела (ТЗ §9, макет — экраны 2 и 4): картинка группы товаров сверху, название
    и подписи из слота под ней. Плитка плоская с границей 1 px, тень — только при наведении.
    $zone (App\Support\CategoryZone, облик «Холод и жар») красит плитку главной: холодильные
    разделы — голубым, тепловые — оранжевым; белый фон фото растворяется в цвете плитки.
    На странице раздела подразделы идут компактными плитками. На телефоне плитка в две
    колонки, поэтому название и поля у неё мельче: длинное слово не рвётся посередине.
--}}
@php
    use App\Support\CategoryZone;

    $tint = match ($zone) {
        CategoryZone::COLD => ['tile' => 'border-cold-line bg-cold-bg', 'name' => 'text-cold-ink', 'picture' => 'bg-cold-bg'],
        CategoryZone::HOT => ['tile' => 'border-hot-line bg-hot-bg', 'name' => 'text-hot-ink', 'picture' => 'bg-hot-bg'],
        default => null,
    };
@endphp

<a
    href="{{ $url }}"
    @if ($zone) data-zone="{{ $zone }}" @endif
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
        :icon-class="$compact ? 'size-8' : 'size-11'"
        :class="$compact ? 'border-b border-line-soft max-md:p-2' : ($tint ? '' : 'border-b border-line-soft')"
    />

    <span @class(['flex flex-1 flex-col gap-1', 'p-3 md:p-3.5' => ! $compact, 'p-3' => $compact])>
        <span @class(['leading-tight font-semibold hyphens-auto wrap-break-word', 'text-base md:text-md' => ! $compact, 'text-base' => $compact, $tint['name'] ?? 'text-ink'])>{{ $name }}</span>
        {{ $slot }}
    </span>
</a>
