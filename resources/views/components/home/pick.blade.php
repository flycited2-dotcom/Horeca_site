@props(['collection'])

{{--
    Карточка подборки «Соберём кухню под задачу» (ТЗ §8.1, облик «Свечение»): пиктограмма, название, описание
    в три строки и сколько моделей в подборке. Вся карточка — ссылка на страницу подборки.
--}}
<a href="{{ route('collection', $collection) }}" {{ $attributes->class('gl-card gl-card--duo gl-pick') }}>
    <span class="gl-pick__ic" aria-hidden="true"><x-ui.equipment-icon :icon="$collection->icon" /></span>
    <h3 class="gl-pick__name">{{ $collection->name }}</h3>
    @if (filled($collection->description))
        <p class="gl-pick__text">{{ $collection->description }}</p>
    @endif
    <span class="gl-pick__foot">
        <span class="gl-pick__count">{{ trans_choice('shop.catalog.models', $collection->listed_count, ['count' => \App\Support\Typography::number($collection->listed_count)]) }}</span>
        <span class="gl-round"><x-home.icon name="arrow" /></span>
    </span>
</a>
