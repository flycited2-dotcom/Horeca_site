<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Письмо со ссылкой на новый пароль (ТЗ §8, §13) в рамке писем магазина. Ссылка
 * собирается при запросе, а не в очереди: так в ней адрес сайта, с которого пришёл клиент.
 */
final class PasswordResetMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $name,
        public readonly string $url,
        public readonly int $minutes,
    ) {
        $this->onQueue('default');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('notifications.password.subject'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.auth.reset-password');
    }
}
