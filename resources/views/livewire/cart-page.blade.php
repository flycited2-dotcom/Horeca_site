{{--
    Корзина (App\Livewire\CartPage; ТЗ §10.1, макет — экран 6): слева строки, справа липкий
    итог с кнопкой «Оформить заявку». Дилерское из макета — шаблоны, спецификация XLSX,
    раздельные отгрузки, счёт в один шаг — после запуска (ТЗ §21).
--}}
@php
    use App\Support\Typography;

    $units = fn (int $count): string => trans_choice('shop.cart.units', $count, ['count' => Typography::number($count)]);
@endphp

<div class="flex flex-col gap-5">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="flex min-w-0 flex-col gap-1.5">
            <h1 class="text-xl font-bold md:text-2xl">{{ __('shop.cart.title') }}</h1>
            @unless ($summary->isEmpty())
                <p class="text-base text-steel-500 tabular">{{ trans_choice('shop.home.positions', $summary->positions(), ['count' => $summary->positions()]) }} · {{ $units($summary->units()) }}</p>
            @endunless
        </div>

        @unless ($summary->isEmpty())
            <form method="post" action="{{ route('cart.clear') }}" wire:submit="clear" wire:confirm="{{ __('shop.cart.clear_confirm') }}">
                @csrf
                @method('DELETE')
                <x-ui.button type="submit" variant="neutral" class="text-sm">{{ __('shop.cart.clear') }}</x-ui.button>
            </form>
        @endunless
    </div>

    @if ($removed)
        <div
            role="status"
            wire:key="removed-{{ $removed['product_id'] }}"
            x-init="setTimeout(() => $wire.forgetRemoved(), 10000)"
            class="flex flex-wrap items-center justify-between gap-3 rounded-card border border-line bg-surface px-4 py-2"
        >
            <span class="text-base">{{ __('shop.cart.removed', ['name' => \Illuminate\Support\Str::limit($removed['name'], 80)]) }}</span>
            <form method="post" action="{{ route('cart.restore', $removed['product_id']) }}" wire:submit="restore">
                @csrf
                <input type="hidden" name="quantity" value="{{ $removed['qty'] }}">
                <x-ui.button type="submit" variant="secondary" class="text-sm">{{ __('shop.cart.restore') }}</x-ui.button>
            </form>
        </div>
    @endif

    @if ($problem)
        <p role="alert" class="rounded-card border border-danger bg-surface px-4 py-3 text-base text-danger-text">{{ $problem }}</p>
    @endif

    @if ($summary->isEmpty())
        <div class="flex max-w-prose flex-col items-start gap-4 rounded-card border border-line bg-surface p-6">
            <p class="text-base text-steel-500">{{ __('shop.cart.empty') }}</p>
            <x-ui.button :href="route('catalog')">{{ __('shop.compare.to_catalog') }}</x-ui.button>
        </div>
    @else
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-start">
            <div class="flex min-w-0 flex-col gap-3" wire:loading.delay.long.class="busy">
                @if ($summary->hasChangedPrices())
                    <p class="rounded-card border border-incoming-line bg-incoming-bg px-4 py-3 text-base text-incoming">{{ __('shop.cart.prices_changed') }}</p>
                @endif

                <ul class="divide-y divide-line-soft overflow-hidden rounded-card border border-line bg-surface">
                    @foreach ($summary->lines as $line)
                        <x-cart.line :line="$line" />
                    @endforeach
                </ul>
            </div>

            <aside class="flex flex-col gap-4 rounded-card border border-line bg-surface p-4 md:p-5 lg:sticky lg:top-21" aria-labelledby="cart-total">
                <h2 id="cart-total" class="text-lg font-semibold">{{ __('shop.cart.total') }}</h2>

                <dl class="flex flex-col gap-2 text-base">
                    <div class="flex justify-between gap-3">
                        <dt class="text-steel-500">{{ trans_choice('shop.home.positions', $summary->positions(), ['count' => $summary->positions()]) }}, {{ $units($summary->units()) }}</dt>
                        <dd class="font-medium whitespace-nowrap tabular">{{ Typography::money($summary->total()) }}</dd>
                    </div>
                    @if ($summary->weightGrams() !== null)
                        <div class="flex justify-between gap-3">
                            <dt class="text-steel-500">{{ __('shop.cart.weight') }}</dt>
                            <dd class="font-medium whitespace-nowrap tabular">{{ Typography::kilograms($summary->weightGrams()) }}</dd>
                        </div>
                    @endif
                </dl>

                <div class="flex items-baseline justify-between gap-3 border-t border-line-soft pt-4">
                    <span class="text-base font-semibold">{{ __('shop.cart.to_pay') }}</span>
                    <span class="text-2xl font-bold whitespace-nowrap tabular">{{ Typography::money($summary->total()) }}</span>
                </div>

                @if ($vat === 'with_vat' || $vat === 'without_vat')
                    <p class="-mt-2 text-sm text-steel-500">{{ __('shop.cart.vat.'.$vat) }}</p>
                @endif

                @if ($wholesalePending)
                    <x-ui.wholesale-pending />
                @endif

                @if ($left = $summary->freeDeliveryLeft())
                    <p class="text-sm text-steel-500">{{ __('shop.cart.free_delivery_left', ['sum' => Typography::money($left)]) }}</p>
                @elseif ($summary->freeDeliveryFrom)
                    <p class="text-sm text-stock-text">{{ __('shop.cart.free_delivery') }}</p>
                @endif

                @if ($summary->canCheckout())
                    <x-ui.button :href="route('checkout')" class="w-full">{{ __('shop.cart.checkout') }}</x-ui.button>
                @else
                    <x-ui.button disabled class="w-full">{{ __('shop.cart.checkout') }}</x-ui.button>
                    <p class="text-sm text-danger-text">{{ __('shop.cart.blocked_checkout') }}</p>
                @endif

                <p class="text-sm text-steel-500">{{ __('shop.cart.next') }}</p>
            </aside>
        </div>
    @endif
</div>
