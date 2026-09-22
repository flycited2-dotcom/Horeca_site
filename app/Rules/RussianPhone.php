<?php

namespace App\Rules;

use App\Support\Phone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Российский номер: десять цифр после +7 или 8 (ТЗ §10.2).
 */
final class RussianPhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || Phone::digits($value) === null) {
            $fail(__('shop.checkout.errors.phone'));
        }
    }
}
