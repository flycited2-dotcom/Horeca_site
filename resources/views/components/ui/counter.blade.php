@props([
    'name' => 'quantity',
    'value' => 1,
    'min' => 1,
    'max' => 9999,
    'id' => null,
    'label' => null,
])

{{--
    Счётчик количества (ТЗ §9, макет — экран 9): кнопки 44×44, поле 52. Значение можно
    ввести вручную; при потере фокуса оно приводится к целому в пределах min…max.
    Без скриптов остаётся обычным числовым полем формы. Кнопки и нормализация сообщают
    об изменении событием change, чтобы на него могли подписаться Livewire и формы.
    Обёртка без overflow-hidden: иначе обрезался бы контур фокуса.
--}}
@php
    $id ??= trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $name), '-');
    $notify = "this.dispatchEvent(new Event('change', { bubbles: true }))";
    $step = "const field = this.parentElement.querySelector('input'); field.%s(); field.dispatchEvent(new Event('change', { bubbles: true }))";
    $button = 'flex size-control items-center justify-center text-title leading-none transition-colors duration-150 ease-out hover:bg-bg';
@endphp

<div
    role="group"
    aria-label="{{ $label ?? __('shop.counter.label') }}"
    {{ $attributes->class('inline-flex shrink-0 rounded-control border border-line bg-surface') }}
>
    <button
        type="button"
        aria-label="{{ __('shop.counter.decrease') }}"
        aria-controls="{{ $id }}"
        onclick="{{ sprintf($step, 'stepDown') }}"
        class="{{ $button }} rounded-l-control"
    >−</button>

    <input
        type="number"
        id="{{ $id }}"
        name="{{ $name }}"
        value="{{ $value }}"
        min="{{ $min }}"
        max="{{ $max }}"
        step="1"
        inputmode="numeric"
        aria-label="{{ __('shop.counter.quantity') }}"
        onblur="const normalized = String(Math.min(this.max, Math.max(this.min, parseInt(this.value, 10) || this.min))); if (this.value !== normalized) { this.value = normalized; {{ $notify }} }"
        class="h-control w-13 appearance-none border-x border-line bg-surface text-center text-md font-medium tabular [-moz-appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
    >

    <button
        type="button"
        aria-label="{{ __('shop.counter.increase') }}"
        aria-controls="{{ $id }}"
        onclick="{{ sprintf($step, 'stepUp') }}"
        class="{{ $button }} rounded-r-control"
    >+</button>
</div>
