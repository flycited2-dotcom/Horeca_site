@props(['products', 'prices', 'view' => 'grid'])

{{--
    Товары листинга плиткой или списком (ТЗ §8.2, макет — экраны 2 и 10). Список — таблица
    с 768 px; на телефоне в обоих видах горизонтальные карточки (экран 14).
--}}
@if ($view === 'list')
    <x-catalog.product-table :products="$products" :prices="$prices" class="max-md:hidden" />
@endif

<div {{ $attributes->class(['grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-6 lg:grid-cols-3', 'md:hidden' => $view === 'list']) }}>
    @foreach ($products as $product)
        <x-catalog.product-card wire:key="card-{{ $product->id }}" :product="$product" :price="$prices[$product->id] ?? null" />
    @endforeach
</div>
