<?php

namespace App\Listeners;

use App\Events\CompanyApproved;
use App\Mail\WholesaleApprovedMail;
use Illuminate\Support\Facades\Mail;

/**
 * «Оптовые цены открыты» (ТЗ §11, §13): письмо на почту компании и на почту кабинета,
 * если они разные. Уходит через очередь default.
 */
final class TellCustomerAboutApproval
{
    public function handle(CompanyApproved $event): void
    {
        $company = $event->company->loadMissing('users:id,company_id,email');

        $recipients = $company->users
            ->pluck('email')
            ->push($company->email)
            ->filter()
            ->map(fn (string $email): string => mb_strtolower($email))
            ->unique()
            ->values()
            ->all();

        if ($recipients !== []) {
            Mail::to($recipients)->queue(new WholesaleApprovedMail($company));
        }
    }
}
