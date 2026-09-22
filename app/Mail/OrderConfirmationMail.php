<?php

namespace App\Mail;

use App\Models\Order;
use App\Services\Settings\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Подтверждение клиенту, если он оставил почту (ТЗ §10.3, §13): номер, состав, сумма
 * и что будет дальше.
 */
final class OrderConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order)
    {
        $this->onQueue('default');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('notifications.order.customer_subject', ['number' => $this->order->number]),
        );
    }

    public function content(): Content
    {
        $settings = app(Settings::class);

        return new Content(view: 'emails.orders.confirmation', with: [
            'phone' => $settings->get('contacts.phones'),
            'email' => $settings->get('contacts.email'),
        ]);
    }
}
