@props(['availability', 'incomingAt' => null])

{{--
    Статус наличия (ТЗ §6.5, §9). Всегда словом, а не только цветом; «Ожидается» —
    с конкретной датой, если поставщик её сообщил.
--}}
@php
    $styles = [
        'in_stock' => 'border-stock-line bg-stock-bg text-stock-text',
        'low' => 'border-stock-line bg-stock-bg text-stock-text',
        'incoming' => 'border-incoming-line bg-incoming-bg text-incoming',
        'on_order' => 'border-dashed border-on-order-line bg-surface text-on-order',
        'discontinued' => 'border-line bg-bg text-steel-500',
    ];

    $label = $availability->getLabel();

    if ($availability === \App\Enums\Availability::Incoming && $incomingAt !== null) {
        $label = __('shop.availability.incoming_at', ['date' => $incomingAt->translatedFormat('j F')]);
    }
@endphp

<span {{ $attributes->class([
    'inline-flex items-center rounded-card border px-2 py-1 text-sm font-medium',
    $styles[$availability->value],
]) }}>{{ $label }}</span>
