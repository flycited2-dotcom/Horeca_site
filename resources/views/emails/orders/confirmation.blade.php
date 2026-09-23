{{-- Подтверждение клиенту (ТЗ §10.3, §13): номер, состав, сумма, что дальше, как связаться. --}}
<x-mail.frame :title="__('notifications.order.customer_subject', ['number' => $order->number])" :heading="__('notifications.order.customer_heading', ['number' => $order->number])">
    <p style="margin:0 0 16px 0;">{{ __('notifications.order.customer_intro', ['name' => $order->customer_name]) }}</p>

    <x-mail.items :order="$order" />

    <h2 style="margin:24px 0 8px 0; font-size:16px;">{{ __('shop.checkout.next_heading') }}</h2>
    <ol style="margin:0; padding-left:20px;">
        <li style="margin-bottom:4px;">{{ __('shop.checkout.next_steps.call') }}</li>
        <li>{{ __('shop.checkout.next_steps.invoice') }}</li>
    </ol>

    <x-mail.contacts :phone="$phone" :email="$email" />
</x-mail.frame>
