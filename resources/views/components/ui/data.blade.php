@props(['label' => null])

{{-- Машинные данные (ТЗ §9): артикул, код 1С, габариты, номера — моноширинным. --}}
<span {{ $attributes->class('font-mono text-sm tabular text-steel-500') }}>
    @if ($label)<span class="sr-only">{{ $label }}: </span>@endif{{ $slot }}
</span>
