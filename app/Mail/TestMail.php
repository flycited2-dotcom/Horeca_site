<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The letter of `php artisan mail:test` (TZ §17.6): it goes out at once, not through the
 * queue, so the answer says whether the mail server took it. In the shop's frame, like the
 * real letters: it also shows how a mail client draws them.
 */
final class TestMail extends Mailable
{
    public function __construct(
        public readonly string $site,
        public readonly string $sender,
        public readonly string $transport,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('backup.mail_test.subject', ['site' => $this->site]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.test');
    }
}
