<?php

namespace App\Mail;

use App\Filament\Resources\ImportRuns\ImportRunResource;
use App\Models\ImportRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the staff that an import did not go through and the catalog was left as it was (TZ §13).
 */
final class ImportFailedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ImportRun $run,
        public readonly string $reason,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('import.mail.failed_subject', ['profile' => $this->run->profile->name]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.import.failed', with: [
            'url' => ImportRunResource::getUrl('view', ['record' => $this->run]),
        ]);
    }
}
