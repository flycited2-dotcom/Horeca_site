@props(['shell', 'wrap' => 'gl-wrap container-page', 'flush' => false])

{{--
    Подвал (облик «Свечение», макет — gl-foot): знак и название магазина, о магазине, телефоны,
    режим работы, почта, адрес и мессенджеры; разделы каталога двумя колонками, страницы для
    покупателей; под ними — «Знаете артикул?» с рабочей формой поиска; внизу — копирайт, оговорка
    об оферте и реквизиты продавца (ТЗ §5.5: «реквизиты продавца для подвала»). Колонка страниц
    появляется, когда менеджер включит хотя бы одну. $flush — подвал главной: шире, чем на
    внутренних страницах, отступ сверху больше.
--}}
<footer {{ $attributes->class(['gl-foot', 'gl-foot--inner' => ! $flush]) }}>
    <div class="{{ $wrap }}">
        <div class="gl-foot__grid">
            <div>
                <x-layout.logo :name="$shell->siteName" :href="route('home')" />
                <p class="gl-foot__about">{{ __('shop.layout.footer.about') }}</p>

                @if ($shell->phones !== [] || $shell->schedule || $shell->email || $shell->address)
                    <ul class="gl-foot__contacts">
                        @foreach ($shell->phones as $phone)
                            <li><a href="{{ $phone['href'] }}" @class(['gl-num', 'gl-foot__phone' => $loop->first])>{{ $phone['label'] }}</a></li>
                        @endforeach
                        @if ($shell->schedule)
                            <li class="gl-foot__note">{{ $shell->schedule }}</li>
                        @endif
                        @if ($shell->email)
                            <li><a href="mailto:{{ $shell->email }}">{{ $shell->email }}</a></li>
                        @endif
                        @if ($shell->address)
                            <li class="gl-foot__note">{{ $shell->address }}</li>
                        @endif
                    </ul>
                @endif

                <x-ui.messengers :links="$shell->messengers" variant="strip" tone="night" class="gl-foot__msgs" />
            </div>

            @if ($shell->categories !== [])
                <nav aria-labelledby="footer-catalog">
                    <h2 id="footer-catalog">{{ __('shop.layout.footer.catalog') }}</h2>

                    <ul class="gl-two">
                        @foreach ($shell->footerCategories() as $category)
                            <li>
                                <a href="{{ route('category', $category['slug']) }}">{{ $category['name'] }}</a>
                            </li>
                        @endforeach
                        <li>
                            <a href="{{ route('catalog') }}" class="gl-foot__more">{{ __('shop.layout.all_categories') }}</a>
                        </li>
                        <li>
                            <a href="{{ route('brands') }}" class="gl-foot__more">{{ __('shop.brands.all') }}</a>
                        </li>
                    </ul>
                </nav>
            @endif

            @if ($shell->footerPages !== [])
                <nav aria-labelledby="footer-customers">
                    <h2 id="footer-customers">{{ __('shop.layout.footer.customers') }}</h2>

                    <ul>
                        @foreach ($shell->footerPages as $page)
                            <li>
                                <a href="{{ $shell->pageUrl($page) }}">{{ $page->title }}</a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            @endif

            <div class="gl-foot__sku">
                <div>
                    <h2>{{ __('shop.layout.footer.sku_heading') }}</h2>
                    <p>{{ __('shop.layout.footer.sku_text') }}</p>
                </div>

                <x-layout.search-form id="footer-search" variant="footer" />
            </div>
        </div>
    </div>

    <div class="gl-foot__bar">
        <div class="{{ $wrap }} flex flex-col gap-3 md:flex-row md:justify-between md:gap-6">
            <div class="flex flex-col gap-2">
                <p>{{ __('shop.layout.copyright', ['year' => now()->year, 'name' => $shell->siteName]) }} {{ __('shop.layout.offer') }}</p>

                @if ($shell->requisites)
                    {{-- Одной строкой: переносы внутри тега показал бы whitespace-pre-line. --}}
                    <p class="max-w-prose whitespace-pre-line"><span class="sr-only">{{ __('shop.layout.requisites') }}: </span>{{ $shell->requisites }}</p>
                @endif
            </div>

            @if ($shell->privacyPage)
                <a href="{{ url($shell->privacyPage->slug) }}" class="gl-foot__policy">{{ $shell->privacyPage->title }}</a>
            @endif
        </div>
    </div>
</footer>
