{{--
    Заявка в кабинете (ТЗ §11): номер и дата, оплата и отгрузка, «Повторить заказ» и счёт PDF,
    когда менеджер его прикрепил; позиции — снимок на момент заявки, итог; справа получение,
    оплата, покупатель и история статусов. В истории — только статусы, о которых клиенту
    пишут письма (§13), с комментарием менеджера к ним: «Новая» и «В работе» — внутренние.
--}}
@php
    use App\Support\Money;
    use App\Support\Typography;
    use App\View\OrderProgress;

    $progress = OrderProgress::of($order);
    $moscow = fn ($date) => $date->timezone('Europe/Moscow');

    $history = [['at' => $order->created_at, 'label' => __('shop.account.order.placed'), 'comment' => null]];

    foreach ($order->statusLogs as $log) {
        if ($log->from_status !== $log->to_status && $log->to_status->isToldToCustomer()) {
            $history[] = ['at' => $log->created_at, 'label' => $log->to_status->getLabel(), 'comment' => $log->comment];
        }
    }

    $delivery = array_filter([
        $order->delivery_method->getLabel(),
        $order->tk_name,
        $order->delivery_city,
        $order->delivery_address,
    ], fn ($value) => filled($value));

    $customer = array_filter([
        $order->company_name,
        filled($order->inn) ? __('shop.account.inn', ['inn' => $order->inn]) : null,
        $order->customer_name,
        $order->phone,
        $order->email,
    ], fn ($value) => filled($value));

    $block = 'flex flex-col gap-1 border-t border-line-soft pt-3 first:border-0 first:pt-0';
    $dt = 'text-sm font-medium text-steel-500';
@endphp

<x-layouts.app :title="__('shop.account.order.title', ['number' => $order->number])" noindex>
    <x-account.frame
        :user="$user"
        :company="$company"
        active="orders"
        :links="[['name' => __('shop.account.orders.title'), 'url' => route('account.orders')]]"
        :current="$order->number"
    >
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex flex-col gap-3">
                <div class="flex flex-col gap-1">
                    <h2 class="text-xl font-bold">
                        {{ __('shop.account.order.heading') }} <span class="font-mono whitespace-nowrap">{{ $order->number }}</span>
                    </h2>
                    <p class="text-base text-steel-500 tabular">{{ __('shop.account.order.placed_at', ['date' => $moscow($order->created_at)->format('d.m.Y H:i')]) }}</p>
                </div>
                <div class="flex flex-wrap gap-x-8 gap-y-3">
                    <div class="flex flex-col gap-1">
                        <span class="text-sm text-steel-500">{{ __('shop.account.orders.payment') }}</span>
                        <x-account.progress :tone="$progress->paymentTone" :label="$progress->payment" :note="$progress->paymentNote" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <span class="text-sm text-steel-500">{{ __('shop.account.orders.shipment') }}</span>
                        <x-account.progress :tone="$progress->shipmentTone" :label="$progress->shipment" />
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-2 max-sm:w-full sm:items-end">
                <div class="flex flex-wrap gap-2 max-sm:flex-col">
                    @if (filled($order->invoice_path))
                        <x-ui.button :href="route('account.order.invoice', $order)" variant="neutral">{{ __('shop.account.order.invoice') }}</x-ui.button>
                    @endif
                    @if ($order->user_id === $user->id)
                        <form method="post" action="{{ route('account.order.repeat', $order) }}" class="max-sm:w-full">
                            @csrf
                            <x-ui.button type="submit" class="max-sm:w-full">{{ __('shop.account.order.repeat') }}</x-ui.button>
                        </form>
                    @endif
                </div>
                <p class="text-sm text-steel-500">{{ __('shop.account.order.repeat_hint') }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-start">
            <section class="rounded-card border border-line bg-surface" aria-labelledby="order-items">
                <h3 id="order-items" class="border-b border-line-soft px-4 py-3.5 text-title font-semibold">{{ __('shop.account.order.items') }}</h3>

                <ul class="divide-y divide-line-soft">
                    @foreach ($order->items as $item)
                        <li class="grid grid-cols-[minmax(0,1fr)_auto] gap-x-4 gap-y-1 px-4 py-3">
                            <div class="flex min-w-0 flex-col gap-1">
                                @if ($item->product?->is_visible)
                                    <a href="{{ route('product', $item->product) }}" class="text-lg font-semibold transition-colors duration-150 ease-out hover:text-accent-ink">{{ $item->name }}</a>
                                @else
                                    <span class="text-lg font-semibold">{{ $item->name }}</span>
                                @endif
                                @if (filled($item->sku))
                                    <x-ui.data :label="__('shop.account.order.sku')">{{ $item->sku }}</x-ui.data>
                                @endif
                                <span class="text-base text-steel-500 tabular">{{ __('shop.account.order.qty', ['qty' => Typography::number($item->qty).Typography::NBSP.$item->unit, 'price' => Typography::money($item->price)]) }}</span>
                            </div>
                            <span class="text-lg font-bold whitespace-nowrap tabular">{{ Typography::money($item->sum) }}</span>
                        </li>
                    @endforeach
                </ul>

                <dl class="flex flex-col gap-2 border-t border-line-soft px-4 py-3.5 text-base">
                    @if (! $order->discount->isZero())
                        <div class="flex justify-between gap-3">
                            <dt class="text-steel-500">{{ __('shop.account.order.subtotal') }}</dt>
                            <dd class="whitespace-nowrap tabular">{{ Typography::money($order->subtotal) }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-steel-500">{{ __('shop.account.order.discount') }}</dt>
                            <dd class="whitespace-nowrap tabular">{{ Typography::money(Money::zero()->subtract($order->discount)) }}</dd>
                        </div>
                    @endif
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-lg font-semibold">{{ __('shop.account.order.total') }}</dt>
                        <dd class="text-2xl font-bold whitespace-nowrap tabular">{{ Typography::money($order->total) }}</dd>
                    </div>
                </dl>
            </section>

            <aside class="flex flex-col gap-4 lg:sticky lg:top-21">
                <dl class="flex flex-col gap-3 rounded-card border border-line bg-surface p-4 text-base">
                    <div class="{{ $block }}">
                        <dt class="{{ $dt }}">{{ __('shop.account.order.delivery') }}</dt>
                        <dd>{{ implode(', ', $delivery) }}</dd>
                    </div>
                    <div class="{{ $block }}">
                        <dt class="{{ $dt }}">{{ __('shop.account.order.payment') }}</dt>
                        <dd>{{ $order->payment_method->getLabel() }}</dd>
                    </div>
                    <div class="{{ $block }}">
                        <dt class="{{ $dt }}">{{ __('shop.account.order.customer') }}</dt>
                        @foreach ($customer as $line)
                            <dd @class(['font-mono text-sm tabular' => $line === $order->phone])>{{ $line }}</dd>
                        @endforeach
                    </div>
                    @if (filled($order->comment))
                        <div class="{{ $block }}">
                            <dt class="{{ $dt }}">{{ __('shop.account.order.comment') }}</dt>
                            <dd class="whitespace-pre-line">{{ $order->comment }}</dd>
                        </div>
                    @endif
                </dl>

                <section class="flex flex-col gap-3 rounded-card border border-line bg-surface p-4" aria-labelledby="order-history">
                    <h3 id="order-history" class="text-lg font-semibold">{{ __('shop.account.order.history') }}</h3>
                    <ol class="flex flex-col gap-3" aria-label="{{ __('shop.account.order.history_label') }}">
                        @foreach ($history as $entry)
                            <li class="flex flex-col gap-0.5 border-l-2 border-line-soft pl-3">
                                <span class="text-base font-medium">{{ $entry['label'] }}</span>
                                <time datetime="{{ $entry['at']->toIso8601String() }}" class="text-sm text-steel-500 tabular">{{ $moscow($entry['at'])->format('d.m.Y H:i') }}</time>
                                @if (filled($entry['comment']))
                                    <span class="text-sm">{{ $entry['comment'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </section>

                @if ($contacts->phones !== [])
                    <p class="text-sm text-steel-500">
                        {{ __('shop.account.order.questions') }}
                        <a href="{{ $contacts->phones[0]['href'] }}" class="font-medium whitespace-nowrap text-ink tabular transition-colors duration-150 ease-out hover:text-accent-ink">{{ $contacts->phones[0]['label'] }}</a>
                    </p>
                @endif
            </aside>
        </div>
    </x-account.frame>
</x-layouts.app>
