@props(['label', 'title', 'sections', 'current' => null, 'filters', 'urlFor'])

{{--
    Ряд разделов, до которых сужается листинг: «Уточнить:» на странице поиска (макет,
    экран 8), «Разделы:» на странице бренда. Выбранный раздел подсвечен; на телефоне ряд
    прокручивается вбок. Ссылки работают без скриптов, Livewire меняет раздел на месте.
    В слот идут ссылки, которые сужают листинг иначе, — «Только в наличии» в поиске.
--}}
<nav aria-label="{{ $label }}" {{ $attributes->class('flex items-center gap-2 max-md:-mx-3 max-md:overflow-x-auto max-md:px-3 max-md:py-1.5 max-md:[scrollbar-width:none] md:flex-wrap') }}>
    <span class="shrink-0 text-sm text-steel-500">{{ $title }}</span>

    @foreach ($sections as $category)
        @php($active = $current?->id === $category->id)
        <a
            href="{{ $urlFor($filters, ['category' => $category->slug]) }}"
            wire:click.prevent="$set('category', '{{ $category->slug }}')"
            wire:key="section-{{ $category->id }}"
            @if ($active) aria-current="true" @endif
            @class([
                'tap-target inline-flex h-8 shrink-0 items-center rounded-control border px-2.5 text-sm font-medium whitespace-nowrap transition-colors duration-150 ease-out hover:border-accent-ink hover:text-accent-ink',
                'border-accent bg-accent-soft text-accent-ink' => $active,
                'border-line bg-surface' => ! $active,
            ])
        >{{ $category->name }} · {{ \App\Support\Typography::number($category->products_count) }}</a>
    @endforeach

    {{ $slot }}
</nav>
