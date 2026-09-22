<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Клиенту о смене статуса заявки (ТЗ §13): подтверждена, выставлен счёт, оплачена,
 * отгружена, выполнена, отменена — с причиной отмены. Внутренние статусы не сообщаются.
 */
final class OrderStatusMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly ?string $comment = null,
    ) {
        $this->onQueue('default');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('notifications.order.status_subject', ['number' => $this->order->number, 'status' => $this->order->status->getLabel()]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.orders.status');
    }
}
