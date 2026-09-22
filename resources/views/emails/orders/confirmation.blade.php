{{-- Подтверждение клиенту (ТЗ §10.3, §13): номер, состав, сумма, что дальше, как связаться. --}}
@php
    $phones = is_array($phone) ? $phone : preg_split('/[,;\n]+/', is_string($phone) ? $phone : '');
    $phones = array_values(array_filter(array_map(fn ($value) => is_string($value) ? trim($value) : '', $phones)));
@endphp

<x-mail.frame :title="__('notifications.order.customer_subject', ['number' => $order->number])" :heading="__('notifications.order.customer_heading', ['number' => $order->number])">
    <p style="margin:0 0 16px 0;">{{ __('notifications.order.customer_intro', ['name' => $order->customer_name]) }}</p>

    <x-mail.items :order="$order" />

    <h2 style="margin:24px 0 8px 0; font-size:16px;">{{ __('shop.checkout.next_heading') }}</h2>
    <ol style="margin:0; padding-left:20px;">
        <li style="margin-bottom:4px;">{{ __('shop.checkout.next_steps.call') }}</li>
        <li>{{ __('shop.checkout.next_steps.invoice') }}</li>
    </ol>

    @if ($phones !== [] || $email)
        <p style="margin:24px 0 0 0; padding-top:16px; border-top:1px solid #e7ebee; color:#5d686f; font-size:14px;">
            {{ __('notifications.order.questions') }}
            @foreach ($phones as $number)
                <a href="tel:{{ preg_replace('/[^+\d]/', '', $number) }}" style="color:#0072b0; white-space:nowrap;">{{ $number }}</a>@if (! $loop->last), @endif
            @endforeach
            @if ($email)
                @if ($phones !== []) · @endif<a href="mailto:{{ $email }}" style="color:#0072b0;">{{ $email }}</a>
            @endif
        </p>
    @endif
</x-mail.frame>
