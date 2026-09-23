{{--
    Главная (ТЗ §8.1, макет — экран 4): плитки корневых разделов с числами вместо баннеров,
    рядом — панель подбора: «Соберём кухню под задачу» (включённые подборки с товарами) и
    «Знаю артикул», ниже — ленты карточек. Внизу — бренды списком названий (ТЗ §8.1, п. 5).
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
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_420px]">
        <section class="flex flex-col gap-5 rounded-card border border-line bg-surface p-4 md:p-6" aria-labelledby="home-heading">
            <div class="flex flex-col gap-2">
                <h1 id="home-heading" class="max-w-[28ch] text-xl font-bold md:text-2xl">{{ __('shop.home.heading') }}</h1>
                @if ($home['products'] > 0)
                    <p class="text-md text-steel-500 tabular">
                        {{ __('shop.home.totals', ['products' => $count('shop.home.positions', $home['products']), 'brands' => $count('shop.home.makers', $home['brands'])]) }}
                    </p>
                @endif
            </div>

            @if ($home['sections'] === [])
                <p class="text-steel-500">{{ __('shop.home.catalog_empty') }}</p>
            @else
                <nav aria-label="{{ __('shop.home.catalog') }}">
                    <ul class="grid grid-cols-2 gap-3 md:grid-cols-3">
                        @foreach ($home['sections'] as $section)
                            <li>
                                <a
                                    href="{{ route('category', $section['slug']) }}"
                                    class="flex h-full flex-col gap-2.5 rounded-card border border-line bg-surface p-3.5 transition-[border-color,box-shadow] duration-150 ease-out hover:border-accent-ink hover:shadow-raised"
                                >
                                    <x-ui.equipment-icon :icon="$section['icon']" class="size-6 text-steel-500 md:size-8" />
                                    <span class="text-md leading-tight font-semibold hyphens-auto wrap-break-word">{{ $section['name'] }}</span>
                                    <span class="text-sm text-steel-500 tabular">
                                        <span class="md:hidden">{{ $count('shop.home.positions', $section['products_count']) }}</span>
                                        <span class="max-md:hidden">{{ __('shop.home.tile_counts', ['products' => $count('shop.home.positions', $section['products_count']), 'in_stock' => Typography::number($section['in_stock'])]) }}</span>
                                    </span>
                                    @if ($section['children'] !== [])
                                        <span class="text-xs text-steel-500 max-md:hidden">{{ implode(', ', $section['children']) }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>

                <a href="{{ route('catalog') }}" class="tap-target self-start text-base font-medium text-accent-ink transition-colors duration-150 ease-out hover:text-accent-dark">
                    {{ $count('shop.home.all_catalog', $sectionsTotal) }}
                </a>
            @endif
        </section>

        <div class="flex flex-col gap-6 self-start rounded-card bg-slate p-4 md:p-6">
            @if ($collections->isNotEmpty())
                <section class="flex flex-col gap-3" aria-labelledby="collections-heading">
                    <h2 id="collections-heading" class="text-xl font-semibold">{{ __('shop.collections.heading') }}</h2>
                    <ul class="flex flex-col gap-2">
                        @foreach ($collections as $collection)
                            <li>
                                <a
                                    href="{{ route('collection', $collection) }}"
                                    class="flex items-start gap-3 rounded-card border border-slate-line bg-surface p-3 transition-[border-color,box-shadow] duration-150 ease-out hover:border-accent-ink hover:shadow-raised"
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

            <section class="flex flex-col gap-3" aria-labelledby="sku-heading">
                <h2 id="sku-heading" class="text-xl font-semibold">{{ __('shop.search.sku_heading') }}</h2>
                <p class="text-base text-steel-500">{{ __('shop.search.sku_text') }}</p>
                <livewire:instant-search field-id="sku-search" variant="sku" />
            </section>
        </div>
    </div>

    @foreach ($strips as $key => $products)
        <section class="mt-10" aria-labelledby="strip-{{ $key }}">
            <h2 id="strip-{{ $key }}" class="text-xl font-bold">{{ $titles[$key] }}</h2>

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
                <h2 id="home-brands" class="text-xl font-bold">{{ __('shop.brands.title') }}</h2>
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
