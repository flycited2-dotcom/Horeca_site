{{--
    Главная (ТЗ §8.1, макет — экран 4, облик «Холод и жар»): тёмный первый экран с заголовком,
    кнопками «Открыть каталог» и «Найдём за вас» и цифрами каталога, ниже — плитки всех
    корневых разделов, окрашенные по «температуре» (App\Support\CategoryZone): покупатель
    прокручивает страницу и видит весь каталог целиком. Ниже — подборки «Соберём кухню
    под задачу» (если они включены) и ленты карточек. Внизу — бренды списком названий
    (ТЗ §8.1, п. 5). Поле «Знаю артикул» осталось в шапке и подвале.
--}}
@php
    use App\Support\Typography;

    $count = fn (string $key, int $value): string => trans_choice($key, $value, ['count' => Typography::number($value)]);
    $titles = [
        'in_stock' => __('shop.home.in_stock_strip'),
        'local' => __('shop.home.local', ['warehouse' => $warehouse]),
        'hits' => __('shop.home.hits'),
        'new' => __('shop.home.new'),
    ];
@endphp

<x-layouts.app :title="__('shop.home.title')" :description="__('shop.seo.home_description')">
    <x-slot:hero>
        <section class="bg-night text-white" aria-labelledby="home-heading">
            <div class="container-page grid gap-0 lg:grid-cols-[minmax(0,1.25fr)_minmax(0,1fr)]">
                <div class="flex flex-col gap-4 py-7 md:gap-5 md:py-10 lg:pr-10">
                    <h1 id="home-heading" class="font-display text-[1.75rem] leading-[1.08] font-bold tracking-[-0.02em] text-balance md:text-[2.5rem] xl:text-[2.75rem]">
                        {{ __('shop.home.hero.heading_lead') }} <span class="text-signal-bright">{{ __('shop.home.hero.heading_accent') }}</span>
                    </h1>
                    <p class="max-w-[46ch] text-md leading-normal text-night-text">{{ __('shop.home.hero.text') }}</p>
                    <div class="flex flex-wrap gap-2.5">
                        <x-ui.button :href="route('catalog')">{{ __('shop.home.hero.catalog') }}</x-ui.button>
                        <x-ui.button variant="night" popovertarget="lead-not-found">{{ __('shop.leads.titles.not_found') }}</x-ui.button>
                    </div>
                </div>

                @if ($hero->facts !== [])
                    {{-- Линии между цифрами — фон сетки в зазоре 1 px; на телефоне блок идёт под заголовком во всю ширину. --}}
                    <dl aria-label="{{ __('shop.home.hero.facts_label') }}" class="grid grid-cols-2 gap-px self-stretch border-night-line bg-night-line max-lg:border-t max-md:-mx-3 md:max-lg:-mx-6 lg:border-l">
                        @foreach ($hero->facts as $fact)
                            <div @class([
                                'flex min-h-24 flex-col-reverse justify-start gap-1.5 bg-night px-4 py-4 md:min-h-28 md:px-6 md:py-5',
                                'col-span-2' => $loop->last && $loop->odd,
                            ])>
                                <dt class="text-sm text-night-text">{{ trans_choice($fact['label'], $fact['value']) }}</dt>
                                <dd @class([
                                    'font-display text-[1.625rem] leading-none font-bold tabular md:text-[2rem]',
                                    'text-cold-bright' => $fact['zone'] === \App\Support\CategoryZone::COLD,
                                    'text-hot-bright' => $fact['zone'] === \App\Support\CategoryZone::HOT,
                                ])>{{ Typography::number($fact['value']) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </div>
        </section>
    </x-slot:hero>

    <x-lead.dialog id="lead-not-found" type="not_found" :message-label="__('shop.leads.fields.what')" />

    <section class="flex flex-col gap-4 md:gap-5" aria-labelledby="home-sections">
        <h2 id="home-sections" class="font-display text-xl font-bold md:text-2xl">{{ __('shop.home.catalog') }}</h2>

        @if ($home['sections'] === [])
            <p class="text-steel-500">{{ __('shop.home.catalog_empty') }}</p>
        @else
            <nav aria-label="{{ __('shop.home.catalog') }}">
                <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6">
                    @foreach ($home['sections'] as $section)
                        <li>
                            <x-catalog.category-tile :name="$section['name']" :url="route('category', $section['slug'])" :image="$images[$section['id']] ?? null" :icon="$section['icon']" :zone="\App\Support\CategoryZone::of($section['icon'], $section['name'])" :eager="$loop->index < 4">
                                <span class="text-sm text-steel-500 tabular">
                                    <span class="md:hidden">{{ $count('shop.home.positions', $section['products_count']) }}</span>
                                    {{-- «0 в наличии» звучит как «ничего нет»: без товаров на складах — только число позиций. --}}
                                    <span class="max-md:hidden">{{ $section['in_stock'] > 0 ? __('shop.home.tile_counts', ['products' => $count('shop.home.positions', $section['products_count']), 'in_stock' => Typography::number($section['in_stock'])]) : $count('shop.home.positions', $section['products_count']) }}</span>
                                </span>
                                @if ($section['children'] !== [])
                                    <span class="text-xs text-steel-500 max-md:hidden">{{ implode(', ', $section['children']) }}</span>
                                @endif
                            </x-catalog.category-tile>
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif
    </section>

    @if ($collections->isNotEmpty())
        <section class="mt-10" aria-labelledby="collections-heading">
            <h2 id="collections-heading" class="font-display text-xl font-bold">{{ __('shop.collections.heading') }}</h2>

            <ul class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                @foreach ($collections as $collection)
                    <li>
                        <a
                            href="{{ route('collection', $collection) }}"
                            class="flex h-full items-start gap-3 rounded-card border border-line bg-surface p-4 transition-[border-color,box-shadow] duration-150 ease-out hover:border-accent-ink hover:shadow-raised"
                        >
                            <x-ui.equipment-icon :icon="$collection->icon" class="mt-0.5 size-6 text-steel-500" />
                            <span class="flex min-w-0 flex-col gap-0.5">
                                <span class="text-md leading-tight font-semibold">{{ $collection->name }}</span>
                                @if (filled($collection->description))
                                    <span class="line-clamp-2 text-sm text-steel-500">{{ $collection->description }}</span>
                                @endif
                                <span class="text-sm text-steel-500 tabular">{{ $count('shop.catalog.models', $collection->listed_count) }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @foreach ($strips as $key => $products)
        <section class="mt-10" aria-labelledby="strip-{{ $key }}">
            <h2 id="strip-{{ $key }}" class="font-display text-xl font-bold">{{ $titles[$key] }}</h2>

            <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-6 lg:grid-cols-4">
                @foreach ($products as $product)
                    <x-catalog.product-card :product="$product" :price="$prices[$product->id] ?? null" />
                @endforeach
            </div>
        </section>
    @endforeach

    @if ($brands !== [])
        <section class="mt-10" aria-labelledby="home-brands">
            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <h2 id="home-brands" class="font-display text-xl font-bold">{{ __('shop.brands.title') }}</h2>
                <a href="{{ route('brands') }}" class="tap-target text-base font-medium text-accent-ink transition-colors duration-150 ease-out hover:text-accent-dark">
                    {{ $count('shop.brands.all_count', $brandsTotal) }}
                </a>
            </div>

            <ul class="mt-4 grid grid-cols-2 gap-x-6 rounded-card border border-line bg-surface px-4 py-2 md:grid-cols-4 md:px-6 md:py-4 lg:grid-cols-6">
                @foreach ($brands as $brand)
                    <li class="min-w-0">
                        <a href="{{ route('brand', $brand['slug']) }}" class="flex min-h-control items-center justify-between gap-2 text-base transition-colors duration-150 ease-out hover:text-accent-ink md:min-h-9">
                            <span class="min-w-0 truncate">{{ $brand['name'] }}</span>
                            <span class="shrink-0 text-sm text-steel-500 tabular">{{ Typography::number($brand['products_count']) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts.app>
