@props(['image' => null, 'icon' => null, 'ratio' => 'aspect-[4/3]', 'iconClass' => 'size-11', 'eager' => false, 'tint' => null, 'inset' => 'p-3'])

{{--
    Картинка раздела (ТЗ §9, макет — экраны 2 и 4): загруженная менеджером или фото ходового
    товара раздела. Без картинки — пиктограмма типа оборудования на полотне, как у карточки
    товара без фото. Подпись рядом уже называет раздел, поэтому у картинки пустой alt.
    $eager — картинка в первом экране: грузится сразу, иначе она поздно становится самым крупным элементом страницы.
    $tint — цвет плитки «холодного» или «горячего» раздела без фото: пиктограмма на нём. Фото всегда на белой
    подложке (bg-stage): снимки поставщика сделаны на белом фоне.
    $inset — поле вокруг фото: у витринной плитки главной оно шире.
--}}
<span {{ $attributes->class(['flex items-center justify-center overflow-hidden', $ratio, 'bg-stage '.$inset => $image, ($tint ?? 'bg-bg') => ! $image]) }}>
    @if ($image)
        <img src="{{ $image }}" alt="" loading="{{ $eager ? 'eager' : 'lazy' }}" decoding="async" class="h-full w-full object-contain">
    @else
        <x-ui.equipment-icon :icon="$icon" class="{{ $iconClass }} {{ $tint === null ? 'text-steel-400' : 'opacity-50' }}" />
    @endif
</span>
