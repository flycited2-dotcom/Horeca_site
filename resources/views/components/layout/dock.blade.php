@props(['shell'])

{{--
    Нижняя панель телефона (облик «Свечение», до 820 px): «Корзина» и «Написать в Telegram»; пока
    в «Настройках» нет ссылки на Telegram, вторая кнопка ведёт в каталог. Число позиций в панели
    не показано: оно устарело бы после «В корзину» без перезагрузки — живой счётчик стоит в шапке.
    Каркас не рисует панель, если страница принесла свою (покупка на карточке товара), и на
    корзине и оформлении, где «Корзина» вела бы на ту же страницу. Стили — glow.css (gl-dock).
--}}
@php($telegram = $shell->messenger(\App\Support\Messengers::TELEGRAM))

<nav aria-label="{{ __('shop.layout.quick_actions') }}" {{ $attributes->class('gl-dock') }}>
    <a href="{{ route('cart') }}" class="gl-btn gl-btn--hot">
        <svg class="gl-ic" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9.5" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2.5 3.5h2.8l2.3 11.2h10.4l2-8.2H6.2"/></svg>
        {{ __('shop.cart.title') }}
    </a>

    @if ($telegram)
        <a href="{{ $telegram['href'] }}" target="_blank" rel="noopener" class="gl-btn gl-btn--glass">
            <svg class="gl-ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 3 3 10.3l6.6 2.5L12 20z"/><path d="M9.6 12.8 21 3"/></svg>
            {{ __('shop.messengers.write', ['name' => $telegram['label']]) }}
        </a>
    @else
        <a href="{{ route('catalog') }}" class="gl-btn gl-btn--glass">
            <svg class="gl-ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            {{ __('shop.layout.catalog') }}
        </a>
    @endif
</nav>
