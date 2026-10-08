@props(['title' => null, 'description' => null, 'noindex' => false, 'meta' => null, 'analytics' => []])

{{--
    Каркас витрины (облик «Свечение», ТЗ §9): служебная полоса, живая сцена с плавающей шапкой-таблеткой,
    ряд корневых разделов чипами, первый экран (слот $hero, только у главной) внутри сцены, подвал и,
    на телефоне, нижняя панель «Корзина» и «Написать в Telegram». Сцена лежит за всем содержимым на
    всех страницах. Поле поиска ведёт мгновенную выдачу (InstantSearch). Данные каркаса собирает
    StorefrontLayoutComposer.

    Ширина: главная (слот $hero) строит секции сама, каждую в .gl-wrap макета, поэтому её <main> без полей
    и шапка с подвалом шириной 1200 px; внутренние страницы получают контейнер каталога (container-page)
    у шапки, подвала и <main>. Слот $bottomBar (липкая покупка на карточке товара) заменяет нижнюю панель.
--}}
@php
    $flush = isset($hero);
    $wrap = $flush ? 'gl-wrap' : 'gl-wrap container-page';
    $dock = ! isset($bottomBar) && ! request()->routeIs('cart', 'checkout');
    $current = fn (string $exact, string ...$within): ?string => request()->routeIs($exact) ? 'page' : ($within !== [] && request()->routeIs(...$within) ? 'true' : null);
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @if ($shell->cookieConsent) data-consent="{{ $shell->cookieConsent }}" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#07090C">
    <script>document.documentElement.classList.add('js')</script>
    {{-- Страницы каталога передают готовое App\Services\Seo\Meta (ТЗ §14), служебные — заголовок. --}}
    <title>{{ $meta?->title ?? ($title ? $title.' | '.$shell->siteName : $shell->siteName) }}</title>
    @if ($meta?->description ?? $description)
        <meta name="description" content="{{ $meta?->description ?? $description }}">
    @endif
    @if ($noindex)
        <meta name="robots" content="noindex">
    @elseif ($meta?->robots)
        <meta name="robots" content="{{ $meta->robots }}">
    @endif
    {{--
        Страницы без конструктора мета — главная, каталог, бренды, «Оптовикам» — называют свой адрес без
        параметров запроса: метки utm_source и порядок вывода не должны делать из них отдельные страницы.
    --}}
    @php($canonical = $meta?->canonical ?? (! $noindex && ! $meta && request()->routeIs('home', 'catalog', 'brands', 'wholesale') ? url()->current() : null))
    @if ($canonical)
        <link rel="canonical" href="{{ $canonical }}">
    @endif
    {{-- Значок вкладки и превью ссылки в мессенджерах и соцсетях; у карточки товара в превью — его фото. --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    @php($shareTitle = $meta?->title ?? ($title ? $title.' | '.$shell->siteName : $shell->siteName))
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $shell->siteName }}">
    <meta property="og:locale" content="ru_RU">
    <meta property="og:title" content="{{ $shareTitle }}">
    @if ($meta?->description ?? $description)
        <meta property="og:description" content="{{ $meta?->description ?? $description }}">
    @endif
    <meta property="og:url" content="{{ $canonical ?? url()->current() }}">
    <meta property="og:image" content="{{ $meta?->image ?? asset('og-image.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    @vite(['resources/css/app.css', 'resources/js/storefront.js'])
    {{--
        Яндекс Метрика (ТЗ §14): номер счётчика и события страницы — цели и электронная коммерция.
        Скрипт витрины загружает счётчик и отправляет события только с согласия на аналитику (§15.10).
    --}}
    @if ($shell->metrikaId && $shell->cookieConsent !== \App\Http\Controllers\CookieConsentController::NECESSARY)
        <meta name="metrika" content="{{ $shell->metrikaId }}">
        @php($metrikaEvents = [...(array) session(\App\Services\Analytics\Metrika::SESSION_KEY, []), ...$analytics])
        @if ($metrikaEvents !== [])
            <script type="application/json" data-metrika-events>{!! json_encode($metrikaEvents, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
        @endif
    @endif
    @if (request()->routeIs('home'))
        <script type="application/ld+json">{!! \App\Support\StructuredData::json(\App\Support\StructuredData::organization($shell)) !!}</script>
    @endif
</head>
<body class="flex min-h-screen flex-col">
    <a href="#content" class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-[100] focus:rounded-full focus:border focus:border-line focus:bg-surface focus:px-5 focus:py-3 focus:font-semibold focus:text-ink">
        {{ __('shop.layout.skip_to_content') }}
    </a>

    {{-- Пока заказчик не задал контакты и не включил страницы, полосе нечего показать. --}}
    @if ($shell->phones !== [] || $shell->schedule || $shell->email || $shell->messengers !== [] || $shell->stripPages !== [])
        <div class="gl-top">
            <div class="{{ $wrap }}">
                <div class="gl-top__l">
                    @if ($phone = $shell->phone())
                        <a href="{{ $phone['href'] }}" class="gl-top__phone gl-num">{{ $phone['label'] }}</a>
                    @endif
                    @if ($shell->schedule)
                        <span class="gl-top__note">{{ $shell->schedule }}</span>
                    @endif
                    @if ($shell->email)
                        <a href="mailto:{{ $shell->email }}" class="max-lg:hidden">{{ $shell->email }}</a>
                    @endif
                </div>

                <div class="gl-top__r">
                    @if ($shell->stripPages !== [])
                        <nav aria-label="{{ __('shop.layout.company') }}" class="max-lg:hidden">
                            <ul class="flex items-center gap-1">
                                @foreach ($shell->stripPages as $page)
                                    <li>
                                        <a href="{{ $shell->pageUrl($page) }}">{{ $page->title }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </nav>
                    @endif
                    <x-ui.messengers :links="$shell->messengers" variant="strip" tone="night" class="shrink-0 flex-nowrap" />
                </div>
            </div>
        </div>
    @endif

    <div class="gl-stage flex flex-1 flex-col">
        <x-layout.scene />

        <header class="gl-hdr">
            <div class="{{ $wrap }}">
                <div class="gl-pill">
                    <x-layout.menu :shell="$shell" />

                    <x-layout.logo :name="$shell->siteName" :href="route('home')" />

                    <nav class="gl-nav" aria-label="{{ __('shop.layout.main_nav') }}">
                        <a href="{{ route('catalog') }}" @if ($mark = $current('catalog', 'category', 'product')) aria-current="{{ $mark }}" @endif>{{ __('shop.layout.catalog') }}</a>
                        <a href="{{ route('brands') }}" @if ($mark = $current('brands', 'brand')) aria-current="{{ $mark }}" @endif>{{ __('shop.brands.title') }}</a>
                        <a href="{{ route('wholesale') }}" @if ($mark = $current('wholesale')) aria-current="{{ $mark }}" @endif>{{ __('shop.wholesale.title') }}</a>
                    </nav>

                    <livewire:instant-search field-id="site-search" />

                    <x-layout.header-actions :shell="$shell" />
                </div>
            </div>
        </header>

        <x-layout.category-nav :shell="$shell" :wrap="$wrap" />

        @isset($hero)
            <div class="gl-stage__hero">
                {{ $hero }}
            </div>
        @endisset

        <main id="content" @class(['flex-1', 'container-page py-6 md:py-8' => ! $flush])>
            {{ $slot }}
        </main>

        <x-layout.footer :shell="$shell" :wrap="$wrap" :flush="$flush" />
    </div>

    @if (isset($bottomBar))
        {{-- Липкая полоса внизу страницы (покупка на карточке товара): под ней оставлено место, чтобы она не закрывала футер. --}}
        <div class="h-18 lg:hidden" aria-hidden="true"></div>
        {{ $bottomBar }}
    @elseif ($dock)
        <x-layout.dock :shell="$shell" />
    @endif

    @if ($shell->cookieConsent === null)
        <x-layout.cookie-banner :shell="$shell" :bar="isset($bottomBar)" />
    @endif

    {{-- Уведомления об итоге действия: с перезагрузкой — из сессии, со скриптами — из шаблона ниже. --}}
    <div
        data-notices
        role="status"
        aria-live="polite"
        @if (isset($bottomBar)) data-bar @endif
        class="gl-notices"
    >
        @if (is_array($notice = session('notice')))
            <x-ui.notice :text="$notice['text'] ?? ''" :href="$notice['href'] ?? null" :link="$notice['link'] ?? null" />
        @elseif ($errors->getBag('lead')->any())
            {{-- Форма лида без скриптов вернулась с ошибкой: окно закрыто, поэтому говорим здесь. --}}
            <x-ui.notice :text="__('shop.leads.not_sent', ['error' => $errors->getBag('lead')->first()])" />
        @endif
    </div>
    <template data-notice-template><x-ui.notice /></template>

    {{-- «Запросить цену» из листингов: одно окно на страницу, товар подставляет нажатая кнопка. --}}
    <x-lead.dialog id="lead-price-shared" type="price_request" shared />

    {{-- Livewire загружает скрипт витрины (resources/js/storefront.js), здесь — только его настройки. --}}
    @livewireScriptConfig
</body>
</html>
