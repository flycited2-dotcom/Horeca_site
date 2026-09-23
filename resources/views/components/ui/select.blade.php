@props([
    'name',
    'options' => [],
    'selected' => null,
    'label' => null,
    'id' => null,
    'placeholder' => null,
    'error' => null,
])

{{--
    Выпадающий список (ТЗ §9, макет — экраны 12 и 15c): подпись сверху, высота 44, радиус 6,
    как у поля ввода. Варианты — «значение => подпись». Заглушка-подсказка без значения идёт
    первой и выбрана, пока ничего не выбрано. Ошибка — у поля; без явной берётся ошибка
    валидации по имени. Класс уходит на обёртку, остальные атрибуты — на сам список.
--}}
@php
    $id ??= trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $name), '-');
    $message = $error ?? ($errors ?? null)?->first($name);
    $selected = $selected === null ? null : (string) $selected;
@endphp

<div {{ $attributes->only('class')->class('flex flex-col gap-1.5') }}>
    @if ($label)
        <label for="{{ $id }}" class="text-sm leading-[1.4] font-medium text-steel-500">{{ $label }}</label>
    @endif

    <select
        id="{{ $id }}"
        name="{{ $name }}"
        @if ($message) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        {{ $attributes->except('class')->class([
            'h-control w-full rounded-control border bg-surface px-3 text-base text-ink transition-colors duration-150 ease-out focus:border-accent',
            'border-danger' => $message,
            'border-line' => ! $message,
        ]) }}
    >
        @if ($placeholder !== null)
            <option value="" @selected($selected === null || $selected === '') disabled>{{ $placeholder }}</option>
        @endif
        @foreach ($options as $value => $text)
            <option value="{{ $value }}" @selected($selected === (string) $value)>{{ $text }}</option>
        @endforeach
    </select>

    @if ($message)
        <p id="{{ $id }}-error" class="text-sm text-danger-text">{{ $message }}</p>
    @endif
</div>
