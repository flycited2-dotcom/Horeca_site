<?php

namespace App\Mail;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Заявка на опт менеджерам (ТЗ §11, §13): реквизиты, контакт, комментарий клиента и кнопка
 * в карточку компании в админке, где её одобряют. Повторная проверка — своя тема и пояснение.
 */
final class WholesaleApplicationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Company $company,
        public readonly string $adminUrl,
        public readonly bool $recheck = false,
    ) {
        $this->onQueue('default');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __($this->recheck ? 'notifications.wholesale.recheck_title' : 'notifications.wholesale.managers_subject', ['company' => $this->company->legal_name]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.companies.application');
    }
}
