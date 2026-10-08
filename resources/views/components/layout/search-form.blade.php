@props(['id', 'variant' => 'header', 'placeholder' => null, 'live' => false])

{{--
    Поиск (ТЗ §8.4, облик «Свечение»): обычная GET-форма на /search, работает без скриптов.
    В шапке — стеклянная таблетка gl-sf: лупа-кнопка отправки слева и поле; Enter отправляет форму
    и на телефоне («Найти» на клавиатуре). В подвале и на странице «404» — таблетка gl-find
    с отдельной кнопкой «Найти». Кегль поля 16 px: телефон не увеличивает страницу при фокусе.
    В режиме live поле ведёт мгновенную выдачу (App\Livewire\InstantSearch).
--}}
@php
    $header = $variant === 'header';
    $value = $header && request()->routeIs('search') ? (string) request()->query('q', '') : '';
    $placeholder ??= $header ? __('shop.layout.search_placeholder') : __('shop.layout.footer.sku_placeholder');
@endphp

<form action="{{ route('search') }}" method="get" role="search" {{ $attributes->class([$header ? 'gl-sf' : 'gl-find']) }}>
    @if ($header)
        <button type="submit" class="gl-sf__go tap-target" aria-label="{{ __('shop.layout.search') }}">
            <svg class="gl-ic" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/></svg>
        </button>
    @else
        <svg class="gl-ic gl-ic--lead" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/></svg>
    @endif

    <label for="{{ $id }}" class="sr-only">{{ __('shop.layout.search') }}</label>
    <input
        id="{{ $id }}"
        type="search"
        name="q"
        value="{{ $value }}"
        placeholder="{{ $placeholder }}"
        enterkeyhint="search"
        @if ($live)
            autocomplete="off"
            aria-describedby="{{ $id }}-status"
            wire:model.live.debounce.250ms="query"
            x-on:focus="open = true"
            x-on:input="open = true"
        @endif
    >

    @unless ($header)
        <button type="submit" class="gl-btn gl-btn--hot gl-btn--sm">{{ __('shop.layout.search') }}</button>
    @endunless
</form>
