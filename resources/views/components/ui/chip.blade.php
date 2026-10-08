@props(['href'])

{{--
    Чип применённого фильтра (облик «Свечение», макет — экраны 2 и 14): круглый, с тёплой рамкой,
    крестик снимает фильтр. Это ссылка на адрес без фильтра — работает без скриптов; Livewire
    перехватывает нажатие через wire:click. Высота 36, цель нажатия — 44.
--}}
<a
    href="{{ $href }}"
    {{ $attributes->class('gl-fchip tap-target shrink-0') }}
>
    <span class="sr-only">{{ __('shop.catalog.remove_filter') }}:</span>
    {{ $slot }}
    <svg class="size-3.5 text-steel-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
        <path d="M6 6l12 12M18 6 6 18"/>
    </svg>
</a>
