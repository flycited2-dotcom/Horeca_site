<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Запоминает UTM-метки перехода (ТЗ §5: orders.utm, leads.utm): посетитель пришёл
 * по рекламной ссылке, а заявку отправил через десять страниц — метки остаются в сессии
 * до заявки.
 */
final class RememberUtm
{
    public const string SESSION_KEY = 'utm';

    private const array KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];

    public function handle(Request $request, Closure $next): Response
    {
        $utm = [];

        foreach (self::KEYS as $key) {
            $value = $request->query($key);

            if (is_string($value) && trim($value) !== '') {
                $utm[$key] = mb_substr(trim($value), 0, 150);
            }
        }

        if ($utm !== [] && $request->hasSession()) {
            $request->session()->put(self::SESSION_KEY, $utm);
        }

        return $next($request);
    }
}
