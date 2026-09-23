@props(['orders'])

{{--
    Заявки клиента (макет, экран 7): номер, дата, состав, оплата, отгрузка — две независимые
    колонки, — сумма и «Повторить». С 1024 px — таблица с колонками макета, ниже — карточка
    заказа: номер и сумма сверху, оплата и отгрузка строками с подписями, кнопка во всю
    ширину, карточки лежат прямо на полотне. Одна разметка на оба вида: раскладку задают
    области сетки, рамку таблицы — страница (`lg:` у обёртки).
--}}
@php
    use App\Support\Typography;
    use App\View\OrderProgress;

    $grid = 'lg:grid-cols-[9.5rem_6.5rem_minmax(0,1fr)_11rem_12.5rem_7.5rem] lg:gap-x-4';
    $pair = 'grid grid-cols-[4.75rem_minmax(0,1fr)] gap-2 lg:block';
    $pairLabel = 'text-sm leading-[1.4] text-steel-500 lg:sr-only';
@endphp

<div {{ $attributes }}>
    <div aria-hidden="true" class="hidden border-b border-line-soft px-4 py-2.5 text-sm font-medium text-steel-500 lg:grid {{ $grid }}">
        <span>{{ __('shop.account.orders.number') }}</span>
        <span>{{ __('shop.account.orders.date') }}</span>
        <span>{{ __('shop.account.orders.items') }}</span>
        <span>{{ __('shop.account.orders.payment') }}</span>
        <span>{{ __('shop.account.orders.shipment') }}</span>
        <span class="text-right">{{ __('shop.account.orders.sum') }}</span>
    </div>

    <ul class="flex flex-col gap-2 lg:gap-0 lg:divide-y lg:divide-line-soft">
        @foreach ($orders as $order)
            @php
                $progress = OrderProgress::of($order);
                $first = $order->items->first();
                $rest = $order->items->count() - 1;
            @endphp
            <li
                class="grid grid-cols-[minmax(0,1fr)_auto] gap-x-3 gap-y-2 rounded-card border border-line bg-surface p-3 [grid-template-areas:'num_sum''date_date''items_items''pay_pay''ship_ship''act_act'] lg:items-start lg:rounded-none lg:border-0 lg:px-4 lg:py-3.5 lg:[grid-template-areas:'num_date_items_pay_ship_sum''num_date_items_pay_ship_act'] {{ $grid }}"
            >
                <a href="{{ route('account.order', $order) }}" class="self-baseline font-mono text-md font-semibold text-ink tabular transition-colors duration-150 ease-out [grid-area:num] hover:text-accent-ink">
                    <span class="sr-only">{{ __('shop.account.orders.number') }} </span>{{ $order->number }}
                </a>

                <span class="text-sm leading-[1.4] text-steel-500 tabular [grid-area:date] lg:text-base">
                    <span class="sr-only">{{ __('shop.account.orders.date') }}: </span>{{ $order->created_at->timezone('Europe/Moscow')->format('d.m.Y') }}
                </span>

                <span class="flex min-w-0 flex-col text-base leading-[1.4] [grid-area:items]">
                    <span class="line-clamp-2"><span class="sr-only">{{ __('shop.account.orders.items') }}: </span>{{ $first?->name ?? '—' }}</span>
                    @if ($rest > 0)
                        <span class="text-sm text-steel-500">{{ trans_choice('shop.account.orders.more_items', $rest, ['count' => $rest]) }}</span>
                    @endif
                </span>

                <div class="border-t border-line-soft pt-2 [grid-area:pay] lg:border-0 lg:pt-0 {{ $pair }}">
                    <span class="{{ $pairLabel }}">{{ __('shop.account.orders.payment') }}</span>
                    <x-account.progress :tone="$progress->paymentTone" :label="$progress->payment" :note="$progress->paymentNote" />
                </div>

                <div class="[grid-area:ship] {{ $pair }}">
                    <span class="{{ $pairLabel }}">{{ __('shop.account.orders.shipment') }}</span>
                    <x-account.progress :tone="$progress->shipmentTone" :label="$progress->shipment" />
                </div>

                <span class="self-baseline text-right text-lg font-bold whitespace-nowrap tabular [grid-area:sum] lg:text-[1.0625rem] lg:leading-[1.2]">
                    <span class="sr-only">{{ __('shop.account.orders.sum') }}: </span>{{ Typography::money($order->total) }}
                </span>

                <form method="post" action="{{ route('account.order.repeat', $order) }}" class="[grid-area:act] lg:text-right">
                    @csrf
                    <button
                        type="submit"
                        class="text-accent-ink transition-colors duration-150 ease-out hover:text-accent-dark max-lg:h-control max-lg:w-full max-lg:rounded-control max-lg:border max-lg:border-line max-lg:text-base max-lg:font-medium max-lg:text-ink max-lg:hover:border-accent-ink lg:text-sm"
                    >
                        <span class="lg:hidden">{{ __('shop.account.order.repeat') }}</span><span class="max-lg:hidden">{{ __('shop.account.orders.repeat') }}<span class="sr-only"> {{ $order->number }}</span></span>
                    </button>
                </form>
            </li>
        @endforeach
    </ul>
</div>
