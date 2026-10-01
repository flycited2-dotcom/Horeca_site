<?php

namespace App\Listeners;

use App\Events\LeadCreated;
use App\Filament\Resources\Leads\LeadResource;
use App\Services\Notifications\LeadTelegramMessage;
use App\Services\Notifications\TelegramNotifier;
use App\Services\Settings\Settings;

/**
 * Лид — только в Telegram менеджеров (ТЗ §13): тип, время, товар, сообщение и ссылка на
 * лиды в админке. Имя, телефон и почта — только при notify.telegram_include_contacts (§15);
 * текст собирает LeadTelegramMessage.
 */
final class NotifyAboutNewLead
{
    public function __construct(
        private readonly TelegramNotifier $telegram,
        private readonly Settings $settings,
    ) {}

    public function handle(LeadCreated $event): void
    {
        $this->telegram->send(LeadTelegramMessage::for(
            $event->lead,
            LeadResource::getUrl('index'),
            $this->settings->boolean('notify.telegram_include_contacts', false),
        ));
    }
}
