<?php

namespace App\Services\Notifications;

use App\Enums\DeliveryMethod;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Typography;
use Illuminate\Support\Str;

/**
 * Новая заявка в Telegram менеджеров (ТЗ §10.3, §13): номер, розница или опт, время, сумма,
 * число позиций и до десяти позиций с суммой, получение, оплата, источник и ссылка на заявку
 * в админке. Имя, телефон, почта, организация, адрес и комментарий клиента — только если в
 * настройках включено notify.telegram_include_contacts: Telegram — зарубежный сервис (§15).
 */
final class OrderTelegramMessage
{
    public const int NAMES = 10;

    public static function for(Order $order, string $adminUrl, bool $withContacts): string
    {
        $items = $order->items;

        $lines = [
            __('notifications.order.telegram_title', ['number' => $order->number, 'type' => mb_strtolower($order->type->getLabel())]),
            __('notifications.order.telegram_time', ['time' => $order->created_at->format('d.m.Y H:i')]),
            __('notifications.order.telegram_total', [
                'total' => Typography::money($order->total),
                'positions' => trans_choice('shop.home.positions', $items->count(), ['count' => $items->count()]),
            ]),
        ];

        foreach ($items->take(self::NAMES) as $item) {
            /** @var OrderItem $item */
            $lines[] = '— '.$item->name.' × '.$item->qty.' · '.Typography::money($item->sum);
        }

        if ($items->count() > self::NAMES) {
            $lines[] = trans_choice('notifications.order.telegram_more', $items->count() - self::NAMES, ['count' => $items->count() - self::NAMES]);
        }

        $lines[] = __('notifications.order.telegram_delivery', ['delivery' => self::delivery($order)]);
        $lines[] = __('notifications.order.telegram_payment', ['payment' => $order->payment_method->getLabel()]);

        if ($withContacts) {
            $lines = [...$lines, ...self::contacts($order)];
        }

        $source = UtmSource::describe($order->utm);

        if ($source !== null) {
            $lines[] = __('notifications.order.telegram_source', ['source' => $source]);
        }

        $lines[] = $adminUrl;

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private static function contacts(Order $order): array
    {
        $lines = [__('notifications.order.telegram_contacts', ['name' => $order->customer_name, 'phone' => $order->phone])];

        if (filled($order->email)) {
            $lines[] = __('notifications.order.telegram_email', ['email' => $order->email]);
        }

        if ($order->is_legal_entity && filled($order->company_name)) {
            $lines[] = __('notifications.order.telegram_company', ['company' => $order->company_name, 'inn' => $order->inn ?? '—']);
        }

        if (filled($order->delivery_address)) {
            $lines[] = __('notifications.order.telegram_address', ['address' => $order->delivery_address]);
        }

        if (filled($order->comment)) {
            $lines[] = __('notifications.order.telegram_comment', ['comment' => Str::limit((string) $order->comment, 500)]);
        }

        return $lines;
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
