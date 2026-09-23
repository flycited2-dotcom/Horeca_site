@props(['shell'])

{{--
    Cookie-баннер (ТЗ §15.10; бриф, экран 18): необходимые cookie — корзина, вход, сравнение —
    работают всегда, аналитические (Яндекс Метрика) — только после «Принять все». Показывается,
    пока посетитель не выбрал; форма работает без скриптов, со скриптами баннер прячется на месте.
--}}
<section
    data-cookie-banner
    aria-labelledby="cookie-banner-heading"
    class="fixed inset-x-3 bottom-3 z-50 flex flex-col gap-3 rounded-card border border-line bg-surface p-4 shadow-raised md:inset-x-auto md:bottom-6 md:left-6 md:max-w-[480px]"
>
    <h2 id="cookie-banner-heading" class="text-base font-semibold">{{ __('shop.cookies.heading') }}</h2>
    <p class="text-sm text-steel-500">
        {{ __('shop.cookies.text') }}
        @if ($shell->privacyPage)
            <a href="{{ $shell->pageUrl($shell->privacyPage) }}" class="text-accent-ink underline underline-offset-2">{{ __('shop.cookies.policy') }}</a>
        @endif
    </p>
    <form method="post" action="{{ route('cookie-consent') }}" data-consent-form class="flex flex-wrap gap-2">
        @csrf
        <x-ui.button type="submit" name="consent" value="all" class="max-sm:flex-1">{{ __('shop.cookies.accept') }}</x-ui.button>
        <x-ui.button type="submit" name="consent" value="necessary" variant="neutral" class="max-sm:flex-1">{{ __('shop.cookies.necessary') }}</x-ui.button>
    </form>
</section>
