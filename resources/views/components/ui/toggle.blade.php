@props([
    'name',
    'value' => '1',
    'checked' => false,
    'id' => null,
])

{{--
    Тумблер (ТЗ §9, облик «Свечение», макет — экраны 2 и 9): дорожка 44×26, ручка 20, включённый — оранжевый
    со свечением, ручка тёмная (gl-switch).
    Это настоящий чекбокс с ролью switch: отправляется обычной формой и работает без
    скриптов. Строка высотой 44 — это и есть цель нажатия. Атрибуты уходят на чекбокс.
--}}
@php
    $id ??= trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $name), '-');
@endphp

<label for="{{ $id }}" class="inline-flex min-h-control cursor-pointer items-center gap-2.5 text-base font-medium">
    <input
        type="checkbox"
        role="switch"
        id="{{ $id }}"
        name="{{ $name }}"
        value="{{ $value }}"
        @checked($checked)
        {{ $attributes->class('peer sr-only') }}
    >
    <span aria-hidden="true" class="gl-switch"></span>
    <span>{{ $slot }}</span>
</label>
