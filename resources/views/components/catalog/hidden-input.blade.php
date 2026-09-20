@props(['name', 'value'])

{{-- Переносит состояние фильтров в другую форму: массивы разворачиваются в скобочные имена. --}}
@if (is_array($value))
    @foreach ($value as $key => $item)
        <x-catalog.hidden-input :name="$name.'['.(is_int($key) ? '' : $key).']'" :value="$item" />
    @endforeach
@else
    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
@endif
