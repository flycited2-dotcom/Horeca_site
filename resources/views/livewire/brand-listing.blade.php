{{--
    Листинг бренда (App\Livewire\BrandListing): собран из тех же частей, что листинг
    категории (макет — экраны 2, 10 и 14), только вместо подразделов — «Разделы:», где
    у бренда больше всего моделей, а в «Подборе» нет фильтра по бренду.
--}}
@php
    use App\Support\Typography;

    $models = fn (int $count): string => trans_choice('shop.catalog.models', $count, ['count' => Typography::number($count)]);
    $resetUrl = $urlFor($filters->cleared(), ['category' => '']);
@endphp

<div class="flex flex-col gap-5">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="flex min-w-0 flex-col gap-1.5">
            <h1 class="text-xl font-bold md:text-2xl">{{ $brand->name }}</h1>
            <p class="text-base text-steel-500 tabular" aria-live="polite">
                @if ($narrowed)
                    {{ trans_choice('shop.catalog.models_of', $brandTotal, ['found' => Typography::number($slice->total), 'total' => Typography::number($brandTotal)]) }}
                @else
                    @if ($brand->country)
                        {{ $brand->country }} ·
                    @endif
                    {{ $models($brandTotal) }} · {{ __('shop.catalog.in_stock_count', ['count' => Typography::number($brandInStock)]) }}
                @endif
            </p>
        </div>

        <div class="flex items-center gap-3 max-md:hidden">
            <x-catalog.sort-control :filters="$filters" :sorts="$sorts" :url-for="$urlFor" class="max-lg:hidden" />
            <x-catalog.view-toggle :view="$view" :filters="$filters" :url-for="$urlFor" />
        </div>
    </div>

    @if ($sections->count() > 1)
        <x-catalog.section-links
            :label="__('shop.brands.sections_label', ['brand' => $brand->name])"
            :title="__('shop.brands.sections')"
            :sections="$sections"
            :current="$section"
            :filters="$filters"
            :url-for="$urlFor"
        />
    @endif

    <x-catalog.filter-bar :filters="$filters" :sorts="$sorts" :url-for="$urlFor" :hidden="$hidden" />

    <div class="flex flex-col gap-6 xl:flex-row xl:items-start">
        <div class="max-xl:contents xl:sticky xl:top-21 xl:w-72 xl:shrink-0">
            <x-catalog.filter-panel
                id="catalog-filters"
                :filters="$filters"
                :brands="collect()"
                :price-range="$priceRange"
                :in-stock-count="$inStockCount"
                :total="$slice->total"
                :action="route('brand', $brand)"
                :hidden="$hidden"
                :reset-url="$resetUrl"
            />
        </div>

        <div class="flex min-w-0 flex-1 flex-col gap-5" wire:loading.delay.class="busy">
            <x-catalog.filter-chips :chips="$chips" :reset-url="$resetUrl" />

            @if ($slice->total === 0)
                @if ($narrowed)
                    <x-catalog.empty-results :suggestions="$suggestions" :reset-url="$resetUrl" />
                @else
                    <p class="rounded-card border border-line bg-surface p-6 text-steel-500">{{ __('shop.brands.empty') }}</p>
                @endif
            @else
                <x-catalog.product-grid :products="$slice->products" :prices="$prices" :view="$view" />

                <x-catalog.pagination :slice="$slice" :filters="$filters" :url-for="$urlFor" class="pt-1" />
            @endif
        </div>
    </div>
</div>
