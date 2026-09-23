<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Согласие на cookie (ТЗ §15.10): «Принять все» разрешает Яндекс Метрику, «Только
 * необходимые» — нет. Выбор хранится год в cookie без шифрования: его читает и скрипт
 * витрины. Форма работает без скриптов; со скриптами баннер прячется на месте.
 */
final class CookieConsentController extends Controller
{
    public const string COOKIE = 'cookie_consent';

    public const string ALL = 'all';

    public const string NECESSARY = 'necessary';

    public const int LIFETIME_MINUTES = 60 * 24 * 365;

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $consent = $request->validate(['consent' => ['required', Rule::in([self::ALL, self::NECESSARY])]])['consent'];

        $cookie = cookie(self::COOKIE, $consent, self::LIFETIME_MINUTES, httpOnly: false, sameSite: 'lax');

        $response = $request->expectsJson()
            ? response()->json(['consent' => $consent])
            : redirect()->back(fallback: route('home'));

        return $response->withCookie($cookie);
    }
}
