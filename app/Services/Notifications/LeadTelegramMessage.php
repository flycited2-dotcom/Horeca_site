<?php

namespace App\Services\Notifications;

use App\Models\Lead;
use App\Support\Typography;
use Illuminate\Support\Str;

/**
 * Лид в Telegram менеджеров (ТЗ §13): тип, время, товар с артикулом, ценой и ссылкой на
 * страницу, сообщение, источник и ссылка на лиды в админке. Имя, телефон и почта — только
 * если в настройках включено notify.telegram_include_contacts: Telegram — зарубежный
 * сервис (§15).
 */
final class LeadTelegramMessage
{
    public static function for(Lead $lead, string $adminUrl, bool $withContacts): string
    {
        $lead->loadMissing('product');

        $lines = [
            __('notifications.lead.title', ['type' => mb_strtolower($lead->type->getLabel())]),
            __('notifications.lead.time', ['time' => $lead->created_at->format('d.m.Y H:i')]),
        ];

        if ($lead->product !== null) {
            $product = $lead->product;

            $lines[] = __('notifications.lead.product', ['name' => $product->name]);

            if (filled($product->sku)) {
                $lines[] = __('notifications.lead.sku', ['sku' => $product->sku]);
            }

            $lines[] = __('notifications.lead.price', [
                'price' => $product->retail_price === null ? __('notifications.lead.on_request') : Typography::money($product->retail_price),
            ]);
            $lines[] = __('notifications.lead.link', ['url' => route('product', $product)]);
        }

        if (filled($lead->message)) {
            $lines[] = __('notifications.lead.message', ['message' => Str::limit((string) $lead->message, 500)]);
        }

        if ($withContacts) {
            $lines[] = __('notifications.lead.name', ['name' => filled($lead->name) ? $lead->name : '—']);
            $lines[] = __('notifications.lead.phone', ['phone' => $lead->phone]);

            if (filled($lead->email)) {
                $lines[] = __('notifications.lead.email', ['email' => $lead->email]);
            }
        }

        $source = UtmSource::describe($lead->utm);

        if ($source !== null) {
            $lines[] = __('notifications.lead.source', ['source' => $source]);
        }

        $lines[] = $adminUrl;

        return implode("\n", $lines);
    }
}
