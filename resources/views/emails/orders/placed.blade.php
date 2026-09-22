{{-- Новая заявка менеджерам (ТЗ §10.3, §13): кто, что, куда и ссылка на бланк. --}}
@php
    $row = 'padding:4px 12px 4px 0; color:#5d686f; vertical-align:top; white-space:nowrap;';
    $value = 'padding:4px 0; vertical-align:top;';
@endphp

<x-mail.frame :title="__('notifications.order.manager_subject', ['number' => $order->number, 'total' => \App\Support\Typography::money($order->total)])" :heading="__('notifications.order.manager_heading', ['number' => $order->number])">
    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 16px 0; font-size:14px;">
        <tr><td style="{{ $row }}">{{ __('notifications.order.type') }}</td><td style="{{ $value }}">{{ $order->type->getLabel() }}</td></tr>
        <tr><td style="{{ $row }}">{{ __('notifications.order.customer') }}</td><td style="{{ $value }}">{{ $order->customer_name }}</td></tr>
        <tr><td style="{{ $row }}">{{ __('notifications.order.phone') }}</td><td style="{{ $value }}"><a href="tel:{{ preg_replace('/[^+\d]/', '', $order->phone) }}" style="color:#0072b0;">{{ $order->phone }}</a></td></tr>
        @if ($order->email)
            <tr><td style="{{ $row }}">{{ __('notifications.order.email') }}</td><td style="{{ $value }}"><a href="mailto:{{ $order->email }}" style="color:#0072b0;">{{ $order->email }}</a></td></tr>
        @endif
        @if ($order->is_legal_entity)
            <tr><td style="{{ $row }}">{{ __('notifications.order.company') }}</td><td style="{{ $value }}">{{ $order->company_name }}, {{ __('notifications.order.inn') }} {{ $order->inn }}</td></tr>
        @endif
        <tr>
            <td style="{{ $row }}">{{ __('notifications.order.delivery') }}</td>
            <td style="{{ $value }}">
                {{ $order->delivery_method->getLabel() }}@if ($order->delivery_city), {{ $order->delivery_city }}@endif @if ($order->tk_name)({{ $order->tk_name }})@endif @if ($order->delivery_address)— {{ $order->delivery_address }}@endif
            </td>
        </tr>
        <tr><td style="{{ $row }}">{{ __('notifications.order.payment') }}</td><td style="{{ $value }}">{{ $order->payment_method->getLabel() }}</td></tr>
        @if ($order->comment)
            <tr><td style="{{ $row }}">{{ __('notifications.order.comment') }}</td><td style="{{ $value }}">{!! nl2br(e($order->comment)) !!}</td></tr>
        @endif
    </table>

    <x-mail.items :order="$order" />

    <p style="margin:24px 0 0 0;">
        <a href="{{ $url }}" style="display:inline-block; padding:11px 18px; background-color:#0072b0; color:#ffffff; text-decoration:none; border-radius:6px; font-weight:500;">{{ __('notifications.order.open') }}</a>
    </p>
</x-mail.frame>
