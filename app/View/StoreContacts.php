<?php

namespace App\View;

use App\Services\Settings\Settings;
use App\Support\Messengers;
use App\Support\Phone;

/**
 * Контакты магазина на странице «Контакты» (ТЗ §5.5): телефоны, почта, мессенджеры, адрес, режим работы
 * и реквизиты продавца из «Настроек». Текст страницы пишет администратор, а сами контакты
 * берутся из одного места — те же, что в шапке и подвале.
 */
final readonly class StoreContacts
{
    public const string PAGE = 'kontakty';

    /**
     * @param  list<array{label: string, href: string}>  $phones
     * @param  list<array{key: string, label: string, href: string}>  $messengers
     */
    public function __construct(
        public array $phones,
        public ?string $email,
        public ?string $address,
        public ?string $schedule,
        public ?string $requisites,
        public array $messengers = [],
    ) {}

    public static function from(Settings $settings): self
    {
        $settings->preload('contacts.phones', 'contacts.email', 'contacts.address', 'contacts.schedule', 'seller.requisites', ...array_values(Messengers::KEYS));

        $text = function (string $key) use ($settings): ?string {
            $value = $settings->get($key);

            return is_string($value) && trim($value) !== '' ? trim($value) : null;
        };

        return new self(
            Phone::links($settings->get('contacts.phones')),
            $text('contacts.email'),
            $text('contacts.address'),
            $text('contacts.schedule'),
            $text('seller.requisites'),
            Messengers::links($settings),
        );
    }

    public function isEmpty(): bool
    {
        return $this->phones === [] && $this->email === null && $this->address === null && $this->requisites === null && $this->messengers === [];
    }
}
