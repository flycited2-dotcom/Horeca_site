@props(['icon' => null])

{{--
    Контурная пиктограмма типа оборудования (ТЗ §9): штрих 1,5, без заливки.
    Имя иконки задаёт менеджер у категории; незнакомое имя даёт нейтральный ящик.
--}}
@php
    $paths = [
        'refrigeration' => '<rect x="4" y="2.5" width="16" height="19" rx="2"/><path d="M4 9h16M8 6v1.5M8 12v3"/>',
        'thermal' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="12" cy="13" r="3.5"/><path d="M3 8h18M7 6h.01M10 6h.01"/>',
        'neutral' => '<path d="M3 9h18v2H3zM5 11v9M19 11v9M3 15h18"/>',
        'dishwashing' => '<rect x="4" y="3" width="16" height="18" rx="2"/><circle cx="12" cy="13" r="4"/><path d="M4 8h16"/>',
        'electromechanical' => '<path d="M12 3v4M7.8 7.8 5 5M16.2 7.8 19 5"/><circle cx="12" cy="14" r="6"/>',
        'ventilation' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 12c3-1 5-2.5 5-5 0-1.5-1.5-2-2.5-1-1.2 1.2-2 3.5-2.5 6zM12 12c-1 3-2.5 5-5 5-1.5 0-2-1.5-1-2.5 1.2-1.2 3.5-2 6-2.5z"/>',
    ];

    $path = $paths[$icon] ?? '<rect x="3.5" y="5" width="17" height="14" rx="2"/><path d="M3.5 10h17M9 5v5"/>';
@endphp

<svg
    {{ $attributes->class('shrink-0') }}
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.5"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
>{!! $path !!}</svg>
