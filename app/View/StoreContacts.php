<?php

namespace App\View;

use App\Services\Settings\Settings;
use App\Support\Phone;

/**
 * Контакты магазина на странице «Контакты» (ТЗ §5.5): телефоны, почта, адрес, режим работы
 * и реквизиты продавца из «Настроек». Текст страницы пишет администратор, а сами контакты
 * берутся из одного места — те же, что в шапке и подвале.
 */
final readonly class StoreContacts
{
    public const string PAGE = 'kontakty';

    /**
     * @param  list<array{label: string, href: string}>  $phones
     */
    public function __construct(
        public array $phones,
        public ?string $email,
        public ?string $address,
        public ?string $schedule,
        public ?string $requisites,
    ) {}

    public static function from(Settings $settings): self
    {
        $settings->preload('contacts.phones', 'contacts.email', 'contacts.address', 'contacts.schedule', 'seller.requisites');

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
        );
    }

    public function isEmpty(): bool
    {
        return $this->phones === [] && $this->email === null && $this->address === null && $this->requisites === null;
    }
}
