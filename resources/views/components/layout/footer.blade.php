@props(['shell'])

{{--
    Футер (макет, экран 5): магазин и телефон, разделы каталога, страницы для покупателей,
    поиск по артикулу; внизу — копирайт, оговорка об оферте и реквизиты продавца
    (ТЗ §5.5: «реквизиты продавца для подвала»). Колонка страниц появляется, когда
    менеджер включит хотя бы одну.
--}}
<footer {{ $attributes->class('bg-slate') }}>
    <div @class([
        'container-page grid grid-cols-1 gap-8 py-10 md:grid-cols-2',
        'lg:grid-cols-[1.4fr_1fr_1fr_1.2fr]' => $shell->footerPages !== [],
        'lg:grid-cols-[1.4fr_1fr_1.2fr]' => $shell->footerPages === [],
    ])>
        <div class="flex flex-col gap-3.5">
            <a href="{{ route('home') }}" class="self-start text-title font-bold">{{ $shell->siteName }}</a>

            @if ($shell->phones !== [])
                <div class="flex flex-col gap-1">
                    @foreach ($shell->phones as $phone)
                        <a href="{{ $phone['href'] }}" @class(['self-start tabular', 'text-xl font-bold' => $loop->first, 'text-base font-medium' => ! $loop->first])>{{ $phone['label'] }}</a>
                    @endforeach

                    @if ($shell->schedule)
                        <span class="text-sm text-steel-500">{{ $shell->schedule }}</span>
                    @endif
                </div>
            @endif
        </div>

        @if ($shell->categories !== [])
            <nav aria-labelledby="footer-catalog">
                <h2 id="footer-catalog" class="text-lg font-semibold">{{ __('shop.layout.footer.catalog') }}</h2>

                <ul class="mt-3.5 flex flex-col gap-2.5">
                    @foreach ($shell->footerCategories() as $category)
                        <li>
                            <a href="{{ route('category', $category['slug']) }}" class="text-base text-steel-500 transition-colors duration-150 ease-out hover:text-accent-ink">{{ $category['name'] }}</a>
                        </li>
                    @endforeach
                    <li>
                        <a href="{{ route('catalog') }}" class="text-base font-medium text-accent-dark transition-colors duration-150 ease-out hover:text-ink">{{ __('shop.layout.all_categories') }}</a>
                    </li>
                    <li>
                        <a href="{{ route('brands') }}" class="text-base font-medium text-accent-dark transition-colors duration-150 ease-out hover:text-ink">{{ __('shop.brands.all') }}</a>
                    </li>
                </ul>
            </nav>
        @endif

        @if ($shell->footerPages !== [])
            <nav aria-labelledby="footer-customers">
                <h2 id="footer-customers" class="text-lg font-semibold">{{ __('shop.layout.footer.customers') }}</h2>

                <ul class="mt-3.5 flex flex-col gap-2.5">
                    @foreach ($shell->footerPages as $page)
                        <li>
                            <a href="{{ $shell->pageUrl($page) }}" class="text-base text-steel-500 transition-colors duration-150 ease-out hover:text-accent-ink">{{ $page->title }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif

        <div class="flex flex-col gap-3">
            <h2 class="text-lg font-semibold">{{ __('shop.layout.footer.sku_heading') }}</h2>
            <p class="text-base text-steel-500">{{ __('shop.layout.footer.sku_text') }}</p>

            <x-layout.search-form id="footer-search" variant="footer" />

            @if ($shell->address || $shell->email)
                <p class="flex flex-col text-sm text-steel-500">
                    @if ($shell->address)
                        <span>{{ $shell->address }}</span>
                    @endif
                    @if ($shell->email)
                        <a href="mailto:{{ $shell->email }}" class="self-start transition-colors duration-150 ease-out hover:text-accent-ink">{{ $shell->email }}</a>
                    @endif
                </p>
            @endif
        </div>
    </div>

    <div class="border-t border-slate-line">
        <div class="container-page flex flex-col gap-3 py-4 text-sm text-steel-500 md:flex-row md:justify-between md:gap-6">
            <div class="flex flex-col gap-2">
                <p>{{ __('shop.layout.copyright', ['year' => now()->year, 'name' => $shell->siteName]) }} {{ __('shop.layout.offer') }}</p>

                @if ($shell->requisites)
                    {{-- Одной строкой: переносы внутри тега показал бы whitespace-pre-line. --}}
                    <p class="max-w-prose whitespace-pre-line"><span class="sr-only">{{ __('shop.layout.requisites') }}: </span>{{ $shell->requisites }}</p>
                @endif
            </div>

            @if ($shell->privacyPage)
                <a href="{{ url($shell->privacyPage->slug) }}" class="shrink-0 self-start font-medium text-ink transition-colors duration-150 ease-out hover:text-accent-ink">{{ $shell->privacyPage->title }}</a>
            @endif
        </div>
    </div>
</footer>
