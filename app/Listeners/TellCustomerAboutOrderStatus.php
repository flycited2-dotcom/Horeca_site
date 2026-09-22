<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Mail\OrderStatusMail;
use Illuminate\Support\Facades\Mail;

/**
 * Клиенту — о статусах, которые его касаются (ТЗ §13): подтверждена, выставлен счёт,
 * оплачена, отгружена, выполнена, отменена. «Новая» и «В работе» — внутренние.
 */
final class TellCustomerAboutOrderStatus
{
    public function handle(OrderStatusChanged $event): void
    {
        $order = $event->order;

        if (! $order->status->isToldToCustomer() || blank($order->email)) {
            return;
        }

        Mail::to($order->email)->queue(new OrderStatusMail($order->loadMissing('items'), $event->comment));
    }
}
