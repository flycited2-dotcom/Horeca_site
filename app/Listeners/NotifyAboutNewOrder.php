<?php

namespace App\Listeners;

use App\Enums\UserRole;
use App\Events\OrderCreated;
use App\Filament\Resources\Orders\OrderResource;
use App\Mail\OrderConfirmationMail;
use App\Mail\OrderPlacedMail;
use App\Models\User;
use App\Services\Notifications\OrderTelegramMessage;
use App\Services\Notifications\TelegramNotifier;
use App\Services\Settings\Settings;
use Illuminate\Support\Facades\Mail;

/**
 * Новая заявка (ТЗ §10.3, §13): сообщение в Telegram менеджеров, письмо менеджерам
 * с полной таблицей и подтверждение клиенту, если он оставил почту. Всё уходит через
 * очередь default — ответ покупателю отправка не держит, а падение Telegram или почты
 * не ломает заказ.
 */
final class NotifyAboutNewOrder
{
    public function __construct(
        private readonly TelegramNotifier $telegram,
        private readonly Settings $settings,
    ) {}

    public function handle(OrderCreated $event): void
    {
        $order = $event->order->loadMissing('items');

        $this->telegram->send(OrderTelegramMessage::for(
            $order,
            OrderResource::getUrl('view', ['record' => $order]),
            $this->settings->boolean('notify.telegram_include_contacts', false),
        ));

        $managers = User::query()
            ->where('is_active', true)
            ->whereIn('role', [UserRole::Manager, UserRole::Admin])
            ->pluck('email')
            ->all();

        if ($managers !== []) {
            Mail::to($managers)->queue(new OrderPlacedMail($order));
        }

        if (filled($order->email)) {
            Mail::to($order->email)->queue(new OrderConfirmationMail($order));
        }
    }
}
