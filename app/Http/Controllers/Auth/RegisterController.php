<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RegisterCustomer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Регистрация покупателя (ТЗ §8: /register). Вход — сразу после создания кабинета, гостевые
 * корзина и сравнение переходят в него.
 */
final class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register', ['started' => RegisterRequest::openedAt()]);
    }

    public function store(RegisterRequest $request, RegisterCustomer $register): RedirectResponse
    {
        /** @var array{name: string, email: string, phone: string, password: string} $data */
        $data = $request->safe()->only(['name', 'email', 'phone', 'password']);

        $user = $register->handle($data);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('home'))
            ->with('notice', ['text' => __('shop.auth.register.welcome', ['name' => $user->name])]);
    }
}
