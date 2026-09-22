<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Validator;

/**
 * Антиспам форм витрины (ТЗ §10.4): скрытое поле-ловушка, которое заполняют только
 * роботы, и зашифрованная метка времени открытия формы — отправка быстрее трёх секунд
 * не принимается. Капчи нет.
 */
trait GuardsAgainstBots
{
    /**
     * The trap field: people never see it, robots fill it.
     */
    public const string HONEYPOT = 'website';

    public const int MIN_SECONDS = 3;

    /**
     * The encrypted moment a form was opened, for its hidden «started» field.
     */
    public static function openedAt(): string
    {
        return Crypt::encryptString((string) now()->getTimestamp());
    }

    /**
     * @return callable(Validator): void
     */
    protected function botCheck(string $message): callable
    {
        return function (Validator $validator) use ($message): void {
            if (filled($this->input(self::HONEYPOT)) || ! $this->openedLongEnoughAgo()) {
                $validator->errors()->add('form', $message);
            }
        };
    }

    private function openedLongEnoughAgo(): bool
    {
        try {
            $opened = (int) Crypt::decryptString((string) $this->input('started'));
        } catch (DecryptException) {
            return false;
        }

        return now()->getTimestamp() - $opened >= self::MIN_SECONDS;
    }
}
