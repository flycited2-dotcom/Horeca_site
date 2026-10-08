@props(['shell', 'wrap' => 'gl-wrap container-page'])

{{--
    Корневые разделы под шапкой (облик «Свечение»): чипы со стеклом и цветной точкой «температуры»
    раздела (App\Support\CategoryZone). С 1024 px — ряд плашек: «Каталог» и главные разделы, не
    больше двух строк. Остальные разделы и те главные, что не поместились, лежат под «Ещё»: скрипт
    витрины оставляет там только их, а без скриптов «Ещё» показывает все. Главные разделы отмечает
    менеджер (категория → «Главный раздел»). Ниже 1024 px — одна лента чипов с прокруткой.
--}}
@php
    use App\Support\CategoryZone;

    // Без отметок «Главный раздел» плашками идут первые восемь, остальные — под «Ещё».
    $chip = 'gl-navchip tap-target';
    $featured = $shell->featuredCategories();
    $others = $shell->otherCategories();
    $zoneClass = fn (array $category): string => match (CategoryZone::of($category['icon'], $category['name'])) {
        CategoryZone::COLD => 'gl-cold',
        CategoryZone::HOT => 'gl-hot',
        default => '',
    };
@endphp

<nav aria-label="{{ __('shop.layout.sections') }}" data-priority-nav {{ $attributes->class('gl-chips max-lg:hidden') }}>
    <div class="{{ $wrap }} flex items-start gap-2">
        <a
            href="{{ route('catalog') }}"
            @if ($shell->inCatalog) aria-current="page" @endif
            class="mt-0.5 shrink-0 {{ $chip }} gl-hot gl-navchip--main"
        >
            <svg class="gl-ic size-5" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 7h16M4 12h16M4 17h16"/>
            </svg>
            {{ __('shop.layout.catalog') }}
        </a>

        <ul class="flex h-22 min-w-0 flex-1 flex-wrap content-start overflow-hidden">
            @foreach ($featured as $category)
                <li data-priority-item class="flex h-11 items-center pr-2">
                    <a
                        href="{{ route('category', $category['slug']) }}"
                        @if ($category['id'] === $shell->currentRootId) aria-current="true" @endif
                        class="{{ $chip }} {{ $zoneClass($category) }}"
                    ><i class="gl-navchip__dot" aria-hidden="true"></i>{{ $category['name'] }}</a>
                </li>
            @endforeach
        </ul>

        @if ($shell->categories !== [])
            <details data-dismissable data-priority-more @if ($others !== []) data-priority-always @endif class="group relative mt-0.5 shrink-0">
                <summary class="{{ $chip }} cursor-pointer list-none gap-1 [&::-webkit-details-marker]:hidden">
                    {{ __('shop.layout.more') }}
                    <svg class="gl-ic size-4 transition-transform duration-150 ease-out group-open:rotate-180" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="m6 9 6 6 6-6"/>
                    </svg>
                </summary>

                <ul class="gl-sheet absolute top-full right-0 z-30 mt-2 grid w-max max-w-[min(40rem,calc(100vw-4rem))] grid-cols-2 gap-x-3 p-2">
                    @foreach ($others as $category)
                        <li>
                            <a href="{{ route('category', $category['slug']) }}" class="gl-row justify-between {{ $zoneClass($category) }}">
                                <span>{{ $category['name'] }}</span>
                                <span class="gl-row__n">{{ \App\Support\Typography::number($category['products_count']) }}</span>
                            </a>
                        </li>
                    @endforeach

                    @foreach ($featured as $category)
                        <li data-priority-extra>
                            <a href="{{ route('category', $category['slug']) }}" class="gl-row justify-between {{ $zoneClass($category) }}">
                                <span>{{ $category['name'] }}</span>
                                <span class="gl-row__n">{{ \App\Support\Typography::number($category['products_count']) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif
    </div>
</nav>

@if ($shell->categories !== [])
    <nav aria-label="{{ __('shop.layout.sections') }}" class="gl-ribbon lg:hidden">
        <ul class="gl-ribbon__list">
            @foreach ($shell->categories as $category)
                <li class="shrink-0">
                    <a
                        href="{{ route('category', $category['slug']) }}"
                        @if ($category['id'] === $shell->currentRootId) aria-current="true" @endif
                        class="{{ $chip }} {{ $zoneClass($category) }}"
                    ><i class="gl-navchip__dot" aria-hidden="true"></i>{{ $category['name'] }}</a>
                </li>
            @endforeach
        </ul>
    </nav>
@endif
