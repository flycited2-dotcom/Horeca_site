@props([
    'name',
    'value' => '1',
    'checked' => false,
    'id' => null,
])

{{--
    Тумблер (ТЗ §9, макет — экраны 2 и 9): дорожка 40×24, ручка 18, включённый — accent.
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
    <span
        aria-hidden="true"
        class="relative h-6 w-10 shrink-0 rounded-full bg-line transition-colors duration-150 ease-out peer-checked:bg-accent peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-accent after:absolute after:top-0.75 after:left-0.75 after:size-4.5 after:rounded-full after:bg-surface after:transition-transform after:duration-150 after:ease-out peer-checked:after:translate-x-4"
    ></span>
    <span>{{ $slot }}</span>
</label>
