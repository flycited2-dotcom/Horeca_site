@props(['href'])

{{--
    Чип применённого фильтра (макет, экраны 2 и 14): рамка accent, крестик снимает фильтр.
    Это ссылка на адрес без фильтра — работает без скриптов; Livewire перехватывает
    нажатие через wire:click. Высота 32, цель нажатия — 44.
--}}
<a
    href="{{ $href }}"
    {{ $attributes->class('tap-target inline-flex h-8 shrink-0 items-center gap-2 rounded-full border border-accent bg-surface px-2.5 text-sm leading-none font-medium whitespace-nowrap text-accent-ink transition-colors duration-150 ease-out hover:bg-accent-soft md:rounded-control') }}
>
    <span class="sr-only">{{ __('shop.catalog.remove_filter') }}:</span>
    {{ $slot }}
    <svg class="size-3.5 text-steel-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
        <path d="M6 6l12 12M18 6 6 18"/>
    </svg>
</a>
