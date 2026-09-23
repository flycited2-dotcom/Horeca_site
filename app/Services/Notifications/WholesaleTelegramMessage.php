<?php

namespace App\Services\Notifications;

use App\Models\Company;

/**
 * Заявка на опт в Telegram менеджеров (ТЗ §11, §13): организация, ИНН, тип заведения, город
 * и ссылка на карточку в админке. Имя и телефон контакта — только если в настройках включено
 * notify.telegram_include_contacts: Telegram — зарубежный сервис (§15). Повторная проверка
 * после смены реквизитов в кабинете помечена в заголовке.
 */
final class WholesaleTelegramMessage
{
    public static function for(Company $company, string $adminUrl, bool $withContacts, bool $recheck = false): string
    {
        $lines = [
            __($recheck ? 'notifications.wholesale.recheck_title' : 'notifications.wholesale.telegram_title', ['company' => $company->legal_name]),
            __('notifications.wholesale.telegram_details', [
                'inn' => $company->inn,
                'segment' => $company->segment->getLabel(),
                'city' => filled($company->city) ? ', '.$company->city : '',
            ]),
        ];

        if ($withContacts) {
            $lines[] = __('notifications.wholesale.telegram_contacts', ['name' => $company->contact_person, 'phone' => $company->phone]);
        }

        $lines[] = $adminUrl;

        return implode("\n", $lines);
    }
}
