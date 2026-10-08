@props(['image' => null, 'icon' => null, 'eager' => false])

{{--
    Светлая плашка под фото (облик «Свечение»): снимки поставщика сняты на белом фоне, поэтому лежат на
    белой плашке с мягким свечением зоны карточки (цвет задаёт --gl-glow у карточки). Фото вписано
    с полями плашки и ничем не обрабатывается. Без фото — пиктограмма типа оборудования, как у остальных
    карточек сайта. Фото декоративное: название товара или раздела стоит рядом текстом. $eager — фото первого
    экрана: грузится сразу, остальные — по мере прокрутки.
--}}
<span {{ $attributes->class('gl-plate gl-plate--photo') }}>
    @if ($image)
        <img src="{{ $image }}" alt="" loading="{{ $eager ? 'eager' : 'lazy' }}" @if ($eager) fetchpriority="high" @endif decoding="async">
    @else
        <x-ui.equipment-icon :icon="$icon" class="gl-plate__ic" />
    @endif
</span>
