@props(['shell', 'bar' => false])

{{--
    Cookie-баннер (ТЗ §15.10; облик «Свечение»): необходимые cookie — корзина, вход, сравнение —
    работают всегда, аналитические (Яндекс Метрика) — только после «Принять все». Показывается,
    пока посетитель не выбрал; форма работает без скриптов, со скриптами баннер прячется на месте.
    Стеклянная карточка с двухцветной рамкой; на телефоне стоит над нижней панелью, $bar — страница
    принесла свою липкую полосу (покупка на карточке товара): над ней баннер стоит и на планшете.
--}}
<section
    data-cookie-banner
    aria-labelledby="cookie-banner-heading"
    @if ($bar) data-bar @endif
    class="gl-card gl-card--duo gl-cookie"
>
    <h2 id="cookie-banner-heading">{{ __('shop.cookies.heading') }}</h2>
    <p>
        {{ __('shop.cookies.text') }}
        @if ($shell->privacyPage)
            <a href="{{ $shell->pageUrl($shell->privacyPage) }}">{{ __('shop.cookies.policy') }}</a>
        @endif
    </p>
    <form method="post" action="{{ route('cookie-consent') }}" data-consent-form class="gl-cookie__act">
        @csrf
        <button type="submit" name="consent" value="all" class="gl-btn gl-btn--hot gl-btn--sm max-sm:flex-1">{{ __('shop.cookies.accept') }}</button>
        <button type="submit" name="consent" value="necessary" class="gl-btn gl-btn--glass gl-btn--sm max-sm:flex-1">{{ __('shop.cookies.necessary') }}</button>
    </form>
</section>
