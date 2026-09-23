<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Форма входа на витрине (ТЗ §15.3): не больше 5 попыток в минуту на пару «почта или
 * телефон + адрес». Ошибка живёт у поля (макет, экран 15a) и говорит, сколько попыток осталось.
 */
class LoginRequest extends FormRequest
{
    public const int MAX_ATTEMPTS = 5;

    public const int DECAY_SECONDS = 60;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'login' => mb_strtolower(__('shop.auth.login.login')),
            'password' => mb_strtolower(__('shop.auth.login.password')),
        ];
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        throw ValidationException::withMessages([
            'login' => __('shop.auth.login.throttled', ['seconds' => RateLimiter::availableIn($this->throttleKey())]),
        ]);
    }

    /**
     * Counts a failed attempt and tells the customer how many are left.
     */
    public function rejectAttempt(): ValidationException
    {
        RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);

        $left = RateLimiter::remaining($this->throttleKey(), self::MAX_ATTEMPTS);

        return $left > 0
            ? ValidationException::withMessages(['password' => trans_choice('shop.auth.login.failed', $left, ['count' => $left])])
            : ValidationException::withMessages(['login' => __('shop.auth.login.throttled', ['seconds' => RateLimiter::availableIn($this->throttleKey())])]);
    }

    public function clearAttempts(): void
    {
        RateLimiter::clear($this->throttleKey());
    }

    private function throttleKey(): string
    {
        return 'login:'.Str::transliterate(Str::lower(trim((string) $this->input('login')))).'|'.$this->ip();
    }
}
