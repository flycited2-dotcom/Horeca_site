{{-- Корзина (ТЗ §10.1, макет — экран 6): страница служебная, в поиск не попадает. --}}
<x-layouts.app :title="__('shop.cart.title')" noindex>
    <x-catalog.breadcrumbs :current="__('shop.cart.title')" />

    <div class="mt-3">
        <livewire:cart-page />
    </div>
</x-layouts.app>
