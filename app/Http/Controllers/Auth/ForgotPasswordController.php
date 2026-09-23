<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\SendPasswordResetLink;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Восстановление пароля (ТЗ §8: /forgot-password). Ответ одинаковый, есть такая почта или
 * нет: по нему нельзя узнать, у кого есть кабинет.
 */
final class ForgotPasswordController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(ForgotPasswordRequest $request, SendPasswordResetLink $send): RedirectResponse
    {
        $send->handle((string) $request->validated('email'));

        return redirect()->route('password.request')
            ->withInput($request->only('email'))
            ->with('status', __('shop.auth.forgot.sent', ['minutes' => (int) config('auth.passwords.users.expire')]));
    }
}
