<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ResetCustomerPassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Новый пароль по ссылке из письма (ТЗ §8: /reset-password/{token}). После смены — на вход:
 * так клиент сразу проверяет, что новый пароль работает.
 */
final class ResetPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function store(ResetPasswordRequest $request, ResetCustomerPassword $reset): RedirectResponse
    {
        /** @var array{email: string, token: string, password: string} $credentials */
        $credentials = $request->safe()->only(['email', 'token', 'password']);

        if (! $reset->handle($credentials)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __('shop.auth.reset.invalid')]);
        }

        return redirect()->route('login')
            ->withInput(['login' => $credentials['email']])
            ->with('notice', ['text' => __('shop.auth.reset.done')]);
    }
}
