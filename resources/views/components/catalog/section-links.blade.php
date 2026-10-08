@props(['label', 'title', 'sections', 'current' => null, 'filters', 'urlFor'])

{{--
    Ряд разделов, до которых сужается листинг (облик «Свечение»): «Уточнить:» на странице поиска (макет,
    экран 8), «Разделы:» на странице бренда. Круглые стеклянные чипы, выбранный раздел светится цветом
    своей зоны (App\Support\CategoryZone) и помечен aria-current; на телефоне ряд
    прокручивается вбок. Ссылки работают без скриптов, Livewire меняет раздел на месте.
    В слот идут ссылки, которые сужают листинг иначе, — «Только в наличии» в поиске.
--}}
<nav aria-label="{{ $label }}" {{ $attributes->class('flex items-center gap-2 max-md:-mx-3 max-md:overflow-x-auto max-md:px-3 max-md:py-1.5 max-md:[scrollbar-width:none] md:flex-wrap') }}>
    <span class="shrink-0 text-sm font-semibold text-steel-500">{{ $title }}</span>

    @foreach ($sections as $category)
        @php
            $active = $current?->id === $category->id;
            $zone = \App\Support\CategoryZone::of($category->icon, $category->name);
        @endphp
        <a
            href="{{ $urlFor($filters, ['category' => $category->slug]) }}"
            wire:click.prevent="$set('category', '{{ $category->slug }}')"
            wire:key="section-{{ $category->id }}"
            @if ($active) aria-current="true" @endif
            @class([
                'gl-lchip tap-target shrink-0',
                'gl-cold' => $zone === \App\Support\CategoryZone::COLD,
                'gl-hot' => $zone === \App\Support\CategoryZone::HOT,
            ])
        >{{ $category->name }} · {{ \App\Support\Typography::number($category->products_count) }}</a>
    @endforeach

    {{ $slot }}
</nav>
