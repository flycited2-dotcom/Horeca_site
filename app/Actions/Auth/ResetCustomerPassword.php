<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Новый пароль по ссылке из письма (ТЗ §8: /reset-password/{token}). Ссылка одноразовая и
 * живёт 60 минут (config/auth.php). Вместе с паролем меняется токен «Запомнить»: входы на
 * других устройствах, сохранённые до смены, перестают работать.
 */
final class ResetCustomerPassword
{
    /**
     * @param  array{email: string, token: string, password: string}  $credentials
     * @return bool whether the link was valid and the password changed
     */
    public function handle(array $credentials): bool
    {
        $status = Password::broker()->reset(
            [
                'email' => mb_strtolower($credentials['email']),
                'token' => $credentials['token'],
                'password' => $credentials['password'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        return $status === Password::PASSWORD_RESET;
    }
}
