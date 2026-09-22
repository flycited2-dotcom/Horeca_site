{{-- Клиенту о смене статуса (ТЗ §13): что произошло с заявкой, при отмене — причина. --}}
<x-mail.frame
    :title="__('notifications.order.status_subject', ['number' => $order->number, 'status' => $order->status->getLabel()])"
    :heading="__('notifications.order.status_heading', ['number' => $order->number, 'status' => mb_strtolower($order->status->getLabel())])"
>
    <p style="margin:0 0 16px 0;">{{ __('notifications.order.status_text.'.$order->status->value) }}</p>

    @if ($comment)
        <p style="margin:0 0 16px 0; padding:12px; background-color:#f4f6f7; border-left:3px solid #0098ea;">{!! nl2br(e($comment)) !!}</p>
    @endif

    <x-mail.items :order="$order" />
</x-mail.frame>
