@props(['shell'])

{{--
    Меню каталога на телефоне (облик «Свечение», до 820 px; шире ссылки «Каталог», «Бренды» и
    «Оптовым клиентам» стоят в шапке): круглая кнопка-бургер раскрывает под шапкой стеклянную
    панель с корневыми разделами и их числами, внизу — «Все категории», «Бренды» и служебные
    страницы. Это <details>: открывается и закрывается без скриптов, бургер в открытом меню
    становится крестиком. Скрипт витрины добавляет закрытие по Esc, по нажатию мимо и кнопкой
    «Назад». Раздел окрашен по «температуре» (App\Support\CategoryZone).
--}}
@php
    use App\Support\CategoryZone;
@endphp

<details data-dismissable data-history-overlay {{ $attributes->class('gl-menu group') }}>
    <summary class="gl-round [&::-webkit-details-marker]:hidden">
        <span class="sr-only">{{ __('shop.layout.menu') }}</span>
        <svg class="gl-ic group-open:hidden" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M4 7h16M4 12h16M4 17h16"/>
        </svg>
        <svg class="gl-ic hidden group-open:block" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M6 6l12 12M18 6 6 18"/>
        </svg>
    </summary>

    <div class="gl-sheet absolute inset-x-0 top-full z-40 mt-2 max-h-[calc(100dvh-12rem)] overflow-y-auto p-2">
        @if ($shell->categories !== [])
            <nav aria-label="{{ __('shop.layout.sections') }}">
                <ul>
                    @foreach ($shell->categories as $category)
                        @php
                            $zone = CategoryZone::of($category['icon'], $category['name']);
                            $iconTone = match ($zone) {
                                CategoryZone::COLD => 'text-cold-bright',
                                CategoryZone::HOT => 'text-hot-bright',
                                default => 'text-steel-500',
                            };
                        @endphp
                        <li>
                            <a
                                href="{{ route('category', $category['slug']) }}"
                                @if ($category['id'] === $shell->currentRootId) aria-current="true" @endif
                                @class(['gl-row', 'gl-cold' => $zone === CategoryZone::COLD, 'gl-hot' => $zone === CategoryZone::HOT])
                            >
                                <x-ui.equipment-icon :icon="$category['icon']" class="size-6 {{ $iconTone }}" />
                                <span class="min-w-0 flex-1">{{ $category['name'] }}</span>
                                <span class="gl-row__n">{{ \App\Support\Typography::number($category['products_count']) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif

        <ul class="mt-2 flex flex-col gap-2 border-t border-white/10 p-1 pt-3">
            <li><a href="{{ route('catalog') }}" class="gl-row gl-row--box">{{ __('shop.layout.all_categories') }}</a></li>
            <li><a href="{{ route('brands') }}" class="gl-row gl-row--box">{{ __('shop.brands.title') }}</a></li>
            @foreach ($shell->stripPages as $page)
                <li><a href="{{ $shell->pageUrl($page) }}" class="gl-row gl-row--box">{{ $page->title }}</a></li>
            @endforeach
        </ul>
    </div>
</details>
