<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AuthenticateCustomer;
use App\Actions\Auth\AuthenticationResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Вход и выход покупателя на витрине (ТЗ §8; макет — экран 15a). После входа — туда, куда
 * клиент шёл, иначе в личный кабинет; гостевые корзина и сравнение переходят в кабинет
 * (слушатели события входа).
 */
final class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, AuthenticateCustomer $authenticate): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        $result = $authenticate->handle(
            (string) $request->validated('login'),
            (string) $request->validated('password'),
            $request->boolean('remember'),
        );

        if ($result === AuthenticationResult::Inactive) {
            throw ValidationException::withMessages(['login' => __('shop.auth.login.inactive')]);
        }

        if ($result === AuthenticationResult::Failed) {
            throw $request->rejectAttempt();
        }

        $request->clearAttempts();
        $request->session()->regenerate();

        return redirect()->intended(route('account'))
            ->with('notice', ['text' => __('shop.auth.login.welcome', ['name' => $request->user()?->name])]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('notice', ['text' => __('shop.auth.logged_out')]);
    }
}
