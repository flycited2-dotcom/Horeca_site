@props(['availability', 'incomingAt' => null, 'variant' => 'badge'])

{{--
    Статус наличия (ТЗ §6.5, §9, макет — экраны 2 и 8). Всегда словом, а не только цветом;
    статусы различаются ещё и формой: «В наличии» — пилюля с точкой, «Ожидается» — плашка
    с ромбом, «Под заказ» — пунктирная рамка с квадратом. «Ожидается» — с конкретной датой,
    если поставщик её сообщил. Вариант text — цветное слово без плашки для плотных списков
    (выдача под поиском, экран 5).
--}}
@php
    $styles = [
        'in_stock' => ['rounded-full border-stock-line bg-stock-bg text-stock-text', 'size-1.75 rounded-full bg-stock-dot'],
        'low' => ['rounded-full border-stock-line bg-stock-bg text-stock-text', 'size-1.75 rounded-full bg-stock-dot'],
        'incoming' => ['rounded-xs border-incoming-line bg-incoming-bg text-incoming', 'size-1.75 rotate-45 bg-incoming-dot'],
        'on_order' => ['border-dashed border-on-order-line bg-surface text-on-order', 'size-1.75 bg-on-order-line'],
        'discontinued' => ['rounded-card border-line bg-bg text-steel-500', 'h-0.5 w-1.75 bg-steel-400'],
    ];

    [$badge, $marker] = $styles[$availability->value];

    $label = $availability->getLabel();

    if ($availability === \App\Enums\Availability::Incoming && $incomingAt !== null) {
        $label = __('shop.availability.incoming_at', ['date' => $incomingAt->translatedFormat('j F')]);
    }
@endphp

@if ($variant === 'text')
    <span {{ $attributes->class([
        'text-sm leading-[1.3] font-medium',
        match ($availability->value) {
            'in_stock', 'low' => 'text-stock-text',
            'incoming' => 'text-incoming',
            'on_order' => 'text-on-order',
            default => 'text-steel-500',
        },
    ]) }}>{{ $label }}</span>
@else
    <span {{ $attributes->class([
        'inline-flex items-center gap-1.5 self-start border px-2.5 py-1 text-sm leading-[1.3] font-medium',
        $badge,
    ]) }}><span aria-hidden="true" class="{{ $marker }} shrink-0"></span>{{ $label }}</span>
@endif
