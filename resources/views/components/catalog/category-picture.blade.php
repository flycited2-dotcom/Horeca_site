@props(['image' => null, 'icon' => null, 'ratio' => 'aspect-[4/3]', 'iconClass' => 'size-11', 'eager' => false])

{{--
    Картинка раздела (ТЗ §9, макет — экраны 2 и 4): загруженная менеджером или фото ходового
    товара раздела. Без картинки — пиктограмма типа оборудования на полотне, как у карточки
    товара без фото. Подпись рядом уже называет раздел, поэтому у картинки пустой alt.
    $eager — картинка в первом экране: грузится сразу, иначе она поздно становится самым крупным элементом страницы.
--}}
<span {{ $attributes->class(['flex items-center justify-center overflow-hidden', $ratio, 'bg-surface p-3' => $image, 'bg-bg' => ! $image]) }}>
    @if ($image)
        <img src="{{ $image }}" alt="" loading="{{ $eager ? 'eager' : 'lazy' }}" decoding="async" class="h-full w-full object-contain">
    @else
        <x-ui.equipment-icon :icon="$icon" class="{{ $iconClass }} text-steel-400" />
    @endif
</span>
