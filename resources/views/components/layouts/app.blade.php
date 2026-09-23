@props(['title' => null, 'description' => null, 'noindex' => false])

{{--
    Каркас витрины (макет, экраны 5, 10 и 14): служебная полоса, шапка, ряд корневых
    категорий, футер. Шапка липкая начиная с планшета; на телефоне поиск — отдельной
    строкой, разделы — лентой чипов. Поле поиска ведёт мгновенную выдачу (InstantSearch).
    Данные каркаса собирает StorefrontLayoutComposer.
--}}

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>document.documentElement.classList.add('js')</script>
    <title>{{ $title ? $title.' | '.$shell->siteName : $shell->siteName }}</title>
    @if ($description)
        <meta name="description" content="{{ $description }}">
    @endif
    @if ($noindex)
        <meta name="robots" content="noindex">
    @endif
    @vite(['resources/css/app.css', 'resources/js/storefront.js'])
    @if (request()->routeIs('home'))
        <script type="application/ld+json">{!! \App\Support\StructuredData::json(\App\Support\StructuredData::organization($shell)) !!}</script>
    @endif
</head>
<body class="flex min-h-screen flex-col">
    <a href="#content" class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 focus:rounded-control focus:bg-surface focus:px-4 focus:py-2">
        {{ __('shop.layout.skip_to_content') }}
    </a>

    {{-- Пока заказчик не задал контакты и не включил страницы, полосе нечего показать. --}}
    @if ($shell->phones !== [] || $shell->schedule || $shell->email || $shell->stripPages !== [])
    <div class="bg-slate">
        <div class="container-page flex h-9 items-center justify-between gap-4 text-xs md:h-10 md:gap-6 md:text-sm">
            <div class="flex min-w-0 items-center gap-5">
                @if ($phone = $shell->phone())
                    <a href="{{ $phone['href'] }}" class="shrink-0 font-medium tabular">{{ $phone['label'] }}</a>
                @endif
                @if ($shell->schedule)
                    <span class="truncate text-steel-500">{{ $shell->schedule }}</span>
                @endif
                @if ($shell->email)
                    <a href="mailto:{{ $shell->email }}" class="hidden font-medium lg:inline">{{ $shell->email }}</a>
                @endif
            </div>

            @if ($shell->stripPages !== [])
                <nav aria-label="{{ __('shop.layout.company') }}" class="hidden lg:block">
                    <ul class="flex items-center gap-5">
                        @foreach ($shell->stripPages as $page)
                            <li>
                                <a href="{{ $shell->pageUrl($page) }}" class="font-medium transition-colors duration-150 ease-out hover:text-accent-ink">{{ $page->title }}</a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            @endif
        </div>
    </div>
    @endif

    <header class="stuck-shadow relative z-30 border-b border-line bg-surface md:sticky md:top-0">
        <div class="container-page grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-x-2.5 py-1.5 md:h-17 md:grid-cols-[auto_auto_minmax(0,1fr)_auto] md:gap-x-6 md:py-0 lg:grid-cols-[auto_minmax(0,1fr)_auto]">
            <x-layout.menu :shell="$shell" class="lg:hidden" />

            <a href="{{ route('home') }}" class="justify-self-start text-title font-bold" aria-label="{{ $shell->siteName }} — {{ __('shop.layout.home') }}">
                {{ $shell->siteName }}
            </a>

            <livewire:instant-search
                field-id="site-search"
                class="col-span-3 max-md:-mx-3 max-md:mt-1.5 max-md:-mb-1.5 max-md:border-t max-md:border-line max-md:px-3 max-md:py-2.5 md:col-span-1"
            />

            <x-layout.header-actions :shell="$shell" class="max-md:col-start-3 max-md:row-start-1" />
        </div>
    </header>

    <x-layout.category-nav :shell="$shell" class="hidden lg:block" />

    @if ($shell->categories !== [])
        <nav aria-label="{{ __('shop.layout.sections') }}" class="border-b border-line bg-surface md:hidden">
            <ul class="container-page flex gap-2 overflow-x-auto py-2.5 [scrollbar-width:none]">
                @foreach ($shell->categories as $category)
                    <li class="shrink-0">
                        <a
                            href="{{ route('category', $category['slug']) }}"
                            @if ($category['id'] === $shell->currentRootId) aria-current="true" @endif
                            @class([
                                'tap-target inline-flex h-9 items-center rounded-control px-3 text-sm font-medium whitespace-nowrap',
                                'bg-slate' => $category['id'] === $shell->currentRootId,
                                'bg-line-soft' => $category['id'] !== $shell->currentRootId,
                            ])
                        >{{ $category['name'] }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif

    <main id="content" class="container-page flex-1 py-6 md:py-8">
        {{ $slot }}
    </main>

    <x-layout.footer :shell="$shell" class="mt-8" />

    {{-- Липкая полоса внизу страницы (покупка на карточке товара): под ней оставлено место, чтобы она не закрывала футер. --}}
    @isset($bottomBar)
        <div class="h-18 lg:hidden" aria-hidden="true"></div>
        {{ $bottomBar }}
    @endisset

    {{-- Уведомления об итоге действия: с перезагрузкой — из сессии, со скриптами — из шаблона ниже. --}}
    <div
        data-notices
        role="status"
        aria-live="polite"
        @class([
            'pointer-events-none fixed inset-x-3 bottom-3 z-40 flex flex-col items-center gap-2 md:inset-x-auto md:right-6 md:bottom-6 md:items-end',
            'max-lg:bottom-21' => isset($bottomBar),
        ])
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
</body>
</html>
