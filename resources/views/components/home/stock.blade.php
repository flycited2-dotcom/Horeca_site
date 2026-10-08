@props(['availability'])

{{--
    Статус наличия на карточке главной (ТЗ §6.5, §9): всегда словом и формой маркера. «В наличии» и
    «В наличии: мало» — светлая зелёная пилюля с точкой, «Ожидается» — янтарная с ромбом, «Под заказ» —
    белая с пунктирной рамкой и квадратом. Подпись даёт App\Enums\Availability.
--}}
<span {{ $attributes->class([
    'gl-stock',
    'gl-stock--incoming' => $availability === \App\Enums\Availability::Incoming,
    'gl-stock--order' => $availability === \App\Enums\Availability::OnOrder,
]) }}>{{ $availability->getLabel() }}</span>
