@props([
    'name',
    'label' => null,
    'id' => null,
    'hint' => null,
    'error' => null,
    'mono' => false,
])

{{--
    Поле ввода (ТЗ §9, облик «Свечение», макет — экраны 9 и 15): подпись сверху, высота 44, непрозрачное
    тёмное поле (gl-field) со свечением рамки на фокусе.
    Ошибка живёт у поля — рамка danger и текст под полем, который объясняет, что
    исправить. Без явной ошибки берётся ошибка валидации по имени поля. Моноширинный
    вариант — для машинных данных: ИНН, телефон, артикул. Класс уходит на обёртку,
    остальные атрибуты — на само поле.
--}}
@php
    $id ??= trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $name), '-');
    $message = $error ?? ($errors ?? null)?->first(str_replace(['[', ']'], ['.', ''], rtrim($name, '[]')));
    $describedBy = $message ? $id.'-error' : ($hint ? $id.'-hint' : null);
@endphp

<div {{ $attributes->only('class')->class('flex flex-col gap-1.5') }}>
    @if ($label)
        <label for="{{ $id }}" class="text-sm leading-[1.4] font-medium text-steel-500">{{ $label }}</label>
    @endif

    <input
        id="{{ $id }}"
        name="{{ $name }}"
        @if ($message) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->except('class')->merge(['type' => 'text'])->class([
            'gl-field h-control w-full rounded-control border px-3 text-base',
            'font-mono tabular' => $mono,
            'border-danger' => $message,
        ]) }}
    >

    @if ($message)
        <p id="{{ $id }}-error" class="text-sm text-danger-text">{{ $message }}</p>
    @elseif ($hint)
        <p id="{{ $id }}-hint" class="text-sm text-steel-500">{{ $hint }}</p>
    @endif
</div>
