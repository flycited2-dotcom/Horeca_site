<?php

namespace App\Listeners;

use App\Enums\UserRole;
use App\Events\ImportFailed;
use App\Mail\ImportFailedMail;
use App\Models\User;
use App\Services\Notifications\TelegramNotifier;
use Illuminate\Support\Facades\Mail;

/**
 * A failed import or a threshold that fired goes to Telegram and to the staff by e-mail (TZ §13).
 * A dry run is a manager checking a feed by hand, so it stays silent.
 */
final class NotifyAboutFailedImport
{
    public function __construct(private readonly TelegramNotifier $telegram) {}

    public function handle(ImportFailed $event): void
    {
        if ($event->run->is_dry_run) {
            return;
        }

        $run = $event->run->loadMissing('profile');

        $this->telegram->send(__('import.telegram.failed', [
            'profile' => $run->profile->name,
            'error' => $event->reason,
            'run' => $run->id,
        ]));

        $recipients = User::query()
            ->where('is_active', true)
            ->whereIn('role', [UserRole::Manager, UserRole::Admin])
            ->pluck('email')
            ->all();

        if ($recipients !== []) {
            Mail::to($recipients)->queue(new ImportFailedMail($run, $event->reason));
        }
    }
}
