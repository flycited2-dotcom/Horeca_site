<?php

namespace App\Mail;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Support\Typography;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Новая заявка менеджерам (ТЗ §10.3, §13): полная таблица, контакты клиента и ссылка
 * на бланк в админке.
 */
final class OrderPlacedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order)
    {
        $this->onQueue('default');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('notifications.order.manager_subject', ['number' => $this->order->number, 'total' => Typography::money($this->order->total)]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.orders.placed', with: [
            'url' => OrderResource::getUrl('view', ['record' => $this->order]),
        ]);
    }
}
