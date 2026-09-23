{{-- Карточки избранного или пустое состояние; общий кусок для гостя и вкладки кабинета. --}}
@if ($products->isEmpty())
    <div class="flex max-w-prose flex-col items-start gap-4 rounded-card border border-line bg-surface p-6">
        <p class="text-base text-steel-500">{{ __('shop.favorites.empty') }}</p>
        <x-ui.button :href="route('catalog')">{{ __('shop.favorites.to_catalog') }}</x-ui.button>
    </div>
@else
    <p class="text-base text-steel-500 tabular">{{ trans_choice('shop.catalog.models', $products->count(), ['count' => $products->count()]) }}</p>
    <x-catalog.product-grid :products="$products" :prices="$prices" class="xl:grid-cols-4" />
@endif
