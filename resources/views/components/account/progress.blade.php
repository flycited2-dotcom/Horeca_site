@props(['tone', 'label', 'note' => null])

{{--
    Состояние оплаты или отгрузки (макет, экран 7): слово с маркером — точка у готового,
    ромб у ждущего действия, серый квадрат у нейтрального; под ним подпись. Различается
    словом и формой, а не только цветом.
--}}
@php
    use App\View\OrderProgress;

    $marker = match ($tone) {
        OrderProgress::OK => 'rounded-full bg-stock-dot',
        OrderProgress::WAIT => 'rotate-45 bg-incoming-dot',
        default => 'bg-steel-400',
    };
@endphp

<span {{ $attributes->class('flex flex-col gap-0.75') }}>
    <span class="inline-flex items-center gap-1.5 text-base leading-[1.4] font-medium">
        <span aria-hidden="true" class="size-1.75 shrink-0 {{ $marker }}"></span>{{ $label }}
    </span>
    @if ($note)
        <span class="text-sm text-steel-500">{{ $note }}</span>
    @endif
</span>
