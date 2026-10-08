@props(['name', 'url', 'image' => null, 'icon' => null, 'compact' => false, 'eager' => false])

{{--
    Плитка раздела (ТЗ §9, макет — экраны 2 и 4): картинка группы товаров сверху, название
    и подписи из слота под ней. Плитка плоская с границей 1 px, тень — только при наведении.
    На странице раздела подразделы идут компактными плитками. На телефоне плитка в две
    колонки, поэтому название и поля у неё мельче: длинное слово не рвётся посередине.
--}}
<a
    href="{{ $url }}"
    {{ $attributes->class('group flex h-full flex-col overflow-hidden rounded-card border border-line bg-surface transition-[border-color,box-shadow] duration-150 ease-out hover:border-accent-ink hover:shadow-raised') }}
>
    <x-catalog.category-picture
        :image="$image"
        :icon="$icon"
        :eager="$eager"
        :icon-class="$compact ? 'size-8' : 'size-11'"
        :class="$compact ? 'border-b border-line-soft max-md:p-2' : 'border-b border-line-soft'"
    />

    <span @class(['flex flex-1 flex-col gap-1', 'p-3 md:p-3.5' => ! $compact, 'p-3' => $compact])>
        <span @class(['leading-tight font-semibold hyphens-auto wrap-break-word', 'text-base md:text-md' => ! $compact, 'text-base' => $compact])>{{ $name }}</span>
        {{ $slot }}
    </span>
</a>
