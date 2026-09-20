@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'button',
    'disabled' => false,
])

{{--
    Кнопка дизайн-системы (ТЗ §9): четыре вида, высота 44 px на всех диапазонах,
    сдвига при наведении нет — меняются только цвета.
--}}
@php
    $base = 'inline-flex h-control items-center justify-center gap-2 rounded-control px-4 text-md font-semibold transition-colors duration-150 ease-out';

    $styles = [
        'primary' => 'bg-accent-ink text-white hover:bg-accent-dark',
        'secondary' => 'border border-accent bg-surface text-accent-ink hover:bg-accent-soft',
        'neutral' => 'border border-line bg-surface text-ink hover:border-accent-ink',
    ];

    $classes = $base.' '.($disabled
        ? 'pointer-events-none bg-bg text-steel-400'
        : ($styles[$variant] ?? $styles['primary']));
@endphp

@if ($href && ! $disabled)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button
        type="{{ $type }}"
        @if ($disabled) aria-disabled="true" disabled @endif
        {{ $attributes->class($classes) }}
    >{{ $slot }}</button>
@endif
