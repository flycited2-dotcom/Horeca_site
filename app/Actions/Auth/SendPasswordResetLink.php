<?php

namespace App\Actions\Auth;

use Illuminate\Support\Facades\Password;

/**
 * Ссылка для нового пароля (ТЗ §8: /forgot-password). Письмо уходит, только если такая
 * почта есть; ответ на витрине одинаковый в любом случае — по нему нельзя узнать, у кого
 * есть кабинет. Повторная ссылка той же почте — не чаще раза в минуту (config/auth.php).
 */
final class SendPasswordResetLink
{
    public function handle(string $email): void
    {
        Password::broker()->sendResetLink(['email' => mb_strtolower(trim($email))]);
    }
}
