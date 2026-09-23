<?php

namespace App\Mail;

use App\Models\Company;
use App\Services\Settings\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Подтверждение клиенту, что заявка на опт принята (ТЗ §11, §13): что будет дальше и что
 * уже работает на сайте.
 */
final class WholesaleReceivedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Company $company)
    {
        $this->onQueue('default');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('notifications.wholesale.received_subject'));
    }

    public function content(): Content
    {
        $settings = app(Settings::class);

        return new Content(view: 'emails.companies.received', with: [
            'phone' => $settings->get('contacts.phones'),
            'email' => $settings->get('contacts.email'),
        ]);
    }
}
