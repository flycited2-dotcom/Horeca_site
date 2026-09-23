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
 * «Оптовые цены открыты» (ТЗ §11, §13): компания проверена, цены в каталоге и корзине —
 * по её ценовой группе после входа в кабинет.
 */
final class WholesaleApprovedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Company $company)
    {
        $this->onQueue('default');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('notifications.wholesale.approved_subject'));
    }

    public function content(): Content
    {
        $settings = app(Settings::class);

        return new Content(view: 'emails.companies.approved', with: [
            'phone' => $settings->get('contacts.phones'),
            'email' => $settings->get('contacts.email'),
        ]);
    }
}
