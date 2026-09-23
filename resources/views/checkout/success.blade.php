{{--
    «Спасибо» (ТЗ §10.3): номер заявки, что будет дальше, контакты менеджера и путь
    обратно в каталог. Открывается только в сессии, которая отправила заявку.
--}}
@php
    use App\Support\Typography;

    $phones = is_array($phones) ? $phones : preg_split('/[,;\n]+/', is_string($phones) ? $phones : '');
    $phones = array_values(array_filter(array_map(fn ($phone) => is_string($phone) ? trim($phone) : '', $phones)));
@endphp

<x-layouts.app :title="__('shop.checkout.success_title', ['number' => $order->number])" :analytics="$analytics" noindex>
    <section class="flex max-w-2xl flex-col items-start gap-4 rounded-card border border-line bg-surface p-4 md:p-8" aria-labelledby="success-heading">
        <p class="rounded-full border border-stock-line bg-stock-bg px-3 py-1 text-sm font-medium text-stock-text">{{ __('shop.checkout.success_badge') }}</p>

        <h1 id="success-heading" class="text-2xl font-bold md:text-3xl">
            {{ __('shop.checkout.success_heading') }} <span class="font-mono whitespace-nowrap">{{ $order->number }}</span>
        </h1>

        <p class="text-base text-steel-500 tabular">
            {{ trans_choice('shop.home.positions', $order->items_count, ['count' => $order->items_count]) }} · {{ Typography::money($order->total) }}
        </p>

        <div class="flex flex-col gap-2">
            <h2 class="text-lg font-semibold">{{ __('shop.checkout.next_heading') }}</h2>
            <ol class="flex list-decimal flex-col gap-1.5 pl-5 text-base">
                <li>{{ __('shop.checkout.next_steps.call') }}</li>
                <li>{{ __('shop.checkout.next_steps.invoice') }}</li>
                <li>{{ $order->email ? __('shop.checkout.next_steps.email', ['email' => $order->email]) : __('shop.checkout.next_steps.no_email') }}</li>
            </ol>
        </div>

        @if ($phones !== [] || $email)
            <div class="flex flex-col gap-1 border-t border-line-soft pt-4 text-base">
                <p class="font-semibold">{{ __('shop.checkout.contacts_heading') }}</p>
                @foreach ($phones as $phone)
                    <a href="tel:{{ preg_replace('/[^+\d]/', '', $phone) }}" class="self-start font-medium tabular transition-colors duration-150 ease-out hover:text-accent-ink">{{ $phone }}</a>
                @endforeach
                @if ($email)
                    <a href="mailto:{{ $email }}" class="self-start text-accent-ink transition-colors duration-150 ease-out hover:text-accent-dark">{{ $email }}</a>
                @endif
                @if ($schedule)
                    <p class="text-sm text-steel-500">{{ $schedule }}</p>
                @endif
            </div>
        @endif

        <x-ui.button :href="route('catalog')">{{ __('shop.checkout.continue') }}</x-ui.button>
    </section>
</x-layouts.app>
