@props(['filters', 'brands', 'priceRange' => null, 'action'])

{{--
    Фильтры листинга (макет, экран 2): липкая колонка 264 на десктопе, на мобильном —
    раскрывающийся блок. Работает обычной GET-формой, поэтому каталог фильтруется и без JS.
--}}
<form method="get" action="{{ $action }}" class="lg:sticky lg:top-21 lg:h-fit lg:w-66 lg:shrink-0">
    <details class="rounded-card border border-line bg-surface lg:open:border-line" open>
        <summary class="flex h-control cursor-pointer list-none items-center justify-between px-4 text-md font-semibold lg:cursor-default">
            {{ __('shop.catalog.filters') }}
        </summary>

        <div class="flex flex-col gap-4 border-t border-line-soft p-4">
            <fieldset>
                <legend class="text-sm font-medium text-steel-500">{{ __('shop.catalog.price_from') }}</legend>
                <div class="mt-2 flex items-center gap-2">
                    <input
                        type="number" name="price_from" inputmode="numeric" min="0"
                        value="{{ $filters->priceFrom }}"
                        placeholder="{{ $priceRange ? intdiv($priceRange['min']->kopecks, 100) : '' }}"
                        class="h-control w-full rounded-control border border-line px-3 text-base tabular"
                        aria-label="{{ __('shop.catalog.price_from') }}"
                    >
                    <span class="text-steel-500">—</span>
                    <input
                        type="number" name="price_to" inputmode="numeric" min="0"
                        value="{{ $filters->priceTo }}"
                        placeholder="{{ $priceRange ? intdiv($priceRange['max']->kopecks, 100) : '' }}"
                        class="h-control w-full rounded-control border border-line px-3 text-base tabular"
                        aria-label="{{ __('shop.catalog.price_to') }}"
                    >
                </div>
            </fieldset>

            <x-ui.toggle name="in_stock" :checked="$filters->inStockOnly">{{ __('shop.catalog.in_stock_only') }}</x-ui.toggle>

            @if ($brands->isNotEmpty())
                <fieldset class="border-t border-line-soft pt-4">
                    <legend class="text-sm font-medium text-steel-500">{{ __('shop.catalog.brand') }}</legend>

                    <div class="mt-2 flex max-h-64 flex-col gap-2 overflow-y-auto">
                        @foreach ($brands as $brand)
                            <label class="flex items-center gap-3 text-base">
                                <input type="checkbox" name="brand[]" value="{{ $brand->slug }}"
                                       @checked(in_array($brand->slug, $filters->brands, true))
                                       class="size-5 rounded-sm border-line text-accent-ink focus:ring-accent">
                                <span class="min-w-0 flex-1 truncate">{{ $brand->name }}</span>
                                <span class="text-sm tabular text-steel-500">{{ $brand->products_count }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endif

            <input type="hidden" name="sort" value="{{ $filters->sort->value }}">

            <div class="flex flex-col gap-2 border-t border-line-soft pt-4">
                <x-ui.button type="submit">{{ __('shop.catalog.filters') }}</x-ui.button>
                <x-ui.button variant="neutral" :href="$action">{{ __('shop.catalog.filters_reset') }}</x-ui.button>
            </div>
        </div>
    </details>
</form>
