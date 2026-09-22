<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * ИНН организации (10 цифр) или ИП (12 цифр) с проверкой контрольных цифр: опечатка
 * в одной цифре не пройдёт. Сообщение объясняет, что не так (макет, экран 12).
 */
final class Inn implements ValidationRule
{
    private const array WEIGHTS_10 = [2, 4, 10, 3, 5, 9, 4, 6, 8];

    private const array WEIGHTS_11 = [7, 2, 4, 10, 3, 5, 9, 4, 6, 8];

    private const array WEIGHTS_12 = [3, 7, 2, 4, 10, 3, 5, 9, 4, 6, 8];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $inn = is_string($value) ? preg_replace('/\s+/', '', $value) : '';

        if (! preg_match('/^\d{10}$|^\d{12}$/', (string) $inn)) {
            $fail(__('shop.checkout.errors.inn_length', ['count' => strlen((string) preg_replace('/\D+/', '', (string) $inn))]));

            return;
        }

        if (! self::checksumOk((string) $inn)) {
            $fail(__('shop.checkout.errors.inn_checksum'));
        }
    }

    private static function checksumOk(string $inn): bool
    {
        $digits = array_map(intval(...), str_split($inn));

        if (count($digits) === 10) {
            return self::control($digits, self::WEIGHTS_10) === $digits[9];
        }

        return self::control($digits, self::WEIGHTS_11) === $digits[10]
            && self::control($digits, self::WEIGHTS_12) === $digits[11];
    }

    /**
     * @param  list<int>  $digits
     * @param  list<int>  $weights
     */
    private static function control(array $digits, array $weights): int
    {
        $sum = 0;

        foreach ($weights as $index => $weight) {
            $sum += $digits[$index] * $weight;
        }

        return $sum % 11 % 10;
    }
}
