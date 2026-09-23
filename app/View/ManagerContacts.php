<?php

namespace App\View;

use App\Services\Settings\Settings;
use App\Support\Phone;

/**
 * «Контакты менеджера» в кабинете (ТЗ §11): телефоны, почта и часы работы из настроек
 * магазина. Персонального менеджера у компании в MVP нет — отвечает отдел продаж.
 */
final readonly class ManagerContacts
{
    /**
     * @param  list<array{label: string, href: string}>  $phones
     */
    public function __construct(
        public array $phones,
        public ?string $email,
        public ?string $schedule,
    ) {}

    public static function from(Settings $settings): self
    {
        $settings->preload('contacts.phones', 'contacts.email', 'contacts.schedule');

        $text = function (string $key) use ($settings): ?string {
            $value = $settings->get($key);

            return is_string($value) && trim($value) !== '' ? trim($value) : null;
        };

        return new self(Phone::links($settings->get('contacts.phones')), $text('contacts.email'), $text('contacts.schedule'));
    }

    public function isEmpty(): bool
    {
        return $this->phones === [] && $this->email === null;
    }
}
