<?php

namespace App\Listeners;

use App\Enums\UserRole;
use App\Events\WholesaleApplied;
use App\Filament\Resources\Companies\CompanyResource;
use App\Mail\WholesaleApplicationMail;
use App\Mail\WholesaleReceivedMail;
use App\Models\User;
use App\Services\Notifications\TelegramNotifier;
use App\Services\Notifications\WholesaleTelegramMessage;
use App\Services\Settings\Settings;
use Illuminate\Support\Facades\Mail;

/**
 * Заявка на опт (ТЗ §13): сообщение в Telegram менеджеров, письмо менеджерам со ссылкой на
 * карточку компании и подтверждение клиенту. Всё — через очередь default: ответ клиенту
 * отправка не держит, падение Telegram или почты заявку не ломает.
 */
final class NotifyAboutWholesaleApplication
{
    public function __construct(
        private readonly TelegramNotifier $telegram,
        private readonly Settings $settings,
    ) {}

    public function handle(WholesaleApplied $event): void
    {
        $company = $event->company;
        $adminUrl = CompanyResource::getUrl('view', ['record' => $company]);

        $this->telegram->send(WholesaleTelegramMessage::for(
            $company,
            $adminUrl,
            $this->settings->boolean('notify.telegram_include_contacts', false),
        ));

        $managers = User::query()
            ->where('is_active', true)
            ->whereIn('role', [UserRole::Manager, UserRole::Admin])
            ->pluck('email')
            ->all();

        if ($managers !== []) {
            Mail::to($managers)->queue(new WholesaleApplicationMail($company, $adminUrl));
        }

        Mail::to($company->email)->queue(new WholesaleReceivedMail($company));
    }
}
