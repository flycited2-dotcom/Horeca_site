@props([
    'name',
    'value',
    'title',
    'description' => null,
    'checked' => false,
    'id' => null,
])

{{--
    Выбор карточкой (облик «Свечение», макет — экран 12): способ получения и оплаты. Настоящая радиокнопка
    внутри подписи — работает без скриптов и с клавиатуры; выбранная карточка получает тёплую рамку и
    свечение (gl-choice). Id радиокнопки нужен, чтобы по выбору показывать связанные поля без скриптов
    (group-has-[#id:checked]).
--}}
@php
    $id ??= trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $name.'-'.$value), '-');
@endphp

<label
    for="{{ $id }}"
    {{ $attributes->class('gl-choice') }}
>
    <input
        type="radio"
        id="{{ $id }}"
        name="{{ $name }}"
        value="{{ $value }}"
        @checked($checked)
        class="mt-0.5 size-4.5 shrink-0 accent-accent focus-visible:outline-none"
    >
    <span class="flex min-w-0 flex-col gap-0.5">
        <span class="text-base font-bold text-white">{{ $title }}</span>
        @if ($description)
            <span class="text-sm text-steel-500">{{ $description }}</span>
        @endif
    </span>
</label>
