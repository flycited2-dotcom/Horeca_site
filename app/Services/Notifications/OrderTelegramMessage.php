<?php

namespace App\Services\Notifications;

use App\Enums\DeliveryMethod;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Typography;

/**
 * Новая заявка в Telegram менеджеров (ТЗ §10.3, §13): номер, розница или опт, сумма,
 * число позиций и до десяти названий, город, ссылка на заявку в админке. Имя и телефон —
 * только если в настройках включено notify.telegram_include_contacts: Telegram —
 * зарубежный сервис (§15).
 */
final class OrderTelegramMessage
{
    public const int NAMES = 10;

    public static function for(Order $order, string $adminUrl, bool $withContacts): string
    {
        $items = $order->items;

        $lines = [
            __('notifications.order.telegram_title', ['number' => $order->number, 'type' => mb_strtolower($order->type->getLabel())]),
            __('notifications.order.telegram_total', [
                'total' => Typography::money($order->total),
                'positions' => trans_choice('shop.home.positions', $items->count(), ['count' => $items->count()]),
            ]),
        ];

        foreach ($items->take(self::NAMES) as $item) {
            /** @var OrderItem $item */
            $lines[] = '— '.$item->name.' × '.$item->qty;
        }

        if ($items->count() > self::NAMES) {
            $lines[] = trans_choice('notifications.order.telegram_more', $items->count() - self::NAMES, ['count' => $items->count() - self::NAMES]);
        }

        $lines[] = __('notifications.order.telegram_delivery', ['delivery' => self::delivery($order)]);

        if ($withContacts) {
            $lines[] = __('notifications.order.telegram_contacts', ['name' => $order->customer_name, 'phone' => $order->phone]);
        }

        $lines[] = $adminUrl;

        return implode("\n", $lines);
    }

    private static function delivery(Order $order): string
    {
        $method = $order->delivery_method->getLabel();

        return match ($order->delivery_method) {
            DeliveryMethod::TransportCompany => $method.', '.$order->delivery_city.' ('.$order->tk_name.')',
            DeliveryMethod::CourierCity => $method,
            DeliveryMethod::Pickup => $method,
        };
    }
}
