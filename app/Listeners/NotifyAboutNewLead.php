<?php

namespace App\Listeners;

use App\Events\LeadCreated;
use App\Filament\Resources\Leads\LeadResource;
use App\Services\Notifications\TelegramNotifier;
use App\Services\Settings\Settings;
use Illuminate\Support\Str;

/**
 * Лид — только в Telegram менеджеров (ТЗ §13): тип, товар, сообщение и ссылка на лиды
 * в админке. Имя и телефон — только при notify.telegram_include_contacts (§15).
 */
final class NotifyAboutNewLead
{
    public function __construct(
        private readonly TelegramNotifier $telegram,
        private readonly Settings $settings,
    ) {}

    public function handle(LeadCreated $event): void
    {
        $lead = $event->lead->loadMissing('product:id,name,sku');

        $lines = [__('notifications.lead.title', ['type' => mb_strtolower($lead->type->getLabel())])];

        if ($lead->product !== null) {
            $lines[] = __('notifications.lead.product', [
                'name' => $lead->product->name,
                'sku' => $lead->product->sku ? ' ('.$lead->product->sku.')' : '',
            ]);
        }

        if (filled($lead->message)) {
            $lines[] = __('notifications.lead.message', ['message' => Str::limit((string) $lead->message, 300)]);
        }

        if ($this->settings->boolean('notify.telegram_include_contacts', false)) {
            $lines[] = __('notifications.order.telegram_contacts', ['name' => $lead->name ?? '—', 'phone' => $lead->phone]);
        }

        $lines[] = LeadResource::getUrl('index');

        $this->telegram->send(implode("\n", $lines));
    }
}
