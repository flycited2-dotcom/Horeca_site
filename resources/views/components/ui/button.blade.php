@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'button',
    'disabled' => false,
])

{{--
    Кнопка дизайн-системы (ТЗ §9, облик «Свечение»): главная — круглая оранжевая со свечением и тёмным
    текстом (белый на оранжевом не читается), вторичная и «ночная» — стеклянные, нейтральная — контурная.
    Стили живут в glow.css и glow-cards.css (gl-btn); высота 46 px на всех диапазонах, сдвига при
    наведении нет — меняются только цвет и свечение. Утилиты вызывающего (w-full, text-sm, класс-крючок
    для скриптов) ложатся поверх.
--}}
@php
    $base = 'gl-btn gl-btn--sm';

    $styles = [
        'primary' => 'gl-btn--hot',
        'secondary' => 'gl-btn--glass',
        'neutral' => 'gl-btn--quiet',
        'night' => 'gl-btn--glass',
    ];

    $classes = $base.' '.($disabled
        ? 'pointer-events-none gl-btn--off'
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
