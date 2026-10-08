@props(['id', 'chip', 'lead' => null, 'accent' => null, 'tail' => null, 'sub' => null, 'hint' => null, 'row' => false])

{{--
    Заголовок раздела главной (облик «Свечение», макет — блок sec__head): плашка с двухцветной точкой,
    заголовок со словом-акцентом в градиенте от холода к жару и описание. $hint — подсказка про наведение
    мышью: на телефоне, где гармошка стала списком, её не видно. $row — заголовок и кнопка рядом
    в одну строку (бренды): кнопка приходит слотом.
--}}
<div @class(['gl-sec__head', 'gl-sec__head--row' => $row])>
    <div>
        <p class="gl-chip"><i class="gl-dot" aria-hidden="true"></i>{{ $chip }}</p>
        <h2 class="gl-h2" id="{{ $id }}">@if (filled($lead)){{ $lead }} @endif<span class="gl-grad">{{ $accent }}</span>{{ $tail }}</h2>
        @if (filled($sub))
            <p class="gl-sub">{{ $sub }}@if (filled($hint)) <span class="gl-sub__hint">{{ $hint }}</span>@endif</p>
        @endif
    </div>

    {{ $slot }}
</div>
