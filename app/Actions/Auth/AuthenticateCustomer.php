<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Facades\Auth;

/**
 * Вход на витрине (ТЗ §8, §15; макет — экран 15a): по почте или по телефону в любом
 * виде записи. Телефон ведёт ко входу, только если он принадлежит одному кабинету.
 * Проверку пароля делает защитник сессии — с выравниванием времени ответа, чтобы по
 * нему нельзя было узнать, есть ли такая почта. Закрытый кабинет не входит, даже с верным паролем.
 */
final class AuthenticateCustomer
{
    public function handle(string $login, #[\SensitiveParameter] string $password, bool $remember): AuthenticationResult
    {
        $email = $this->email(trim($login));
        $inactive = false;

        $entered = Auth::guard('web')->attemptWhen(
            ['email' => $email ?? '', 'password' => $password],
            function (User $user) use (&$inactive): bool {
                $inactive = ! $user->is_active;

                return ! $inactive;
            },
            $remember,
        );

        if (! $entered) {
            return $inactive ? AuthenticationResult::Inactive : AuthenticationResult::Failed;
        }

        /** @var User $user */
        $user = Auth::guard('web')->user();
        $user->forceFill(['last_login_at' => now()])->save();

        return AuthenticationResult::Success;
    }

    /**
     * The e-mail the customer signs in with: typed directly or found by the phone.
     */
    private function email(string $login): ?string
    {
        if (str_contains($login, '@')) {
            return mb_strtolower($login);
        }

        $phone = Phone::normalize($login);

        if ($phone === null) {
            return null;
        }

        $emails = User::query()->where('phone', $phone)->limit(2)->pluck('email');

        return $emails->count() === 1 ? $emails->first() : null;
    }
}
