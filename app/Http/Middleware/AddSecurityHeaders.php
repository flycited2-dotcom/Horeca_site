<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Заголовки безопасности для каждого ответа (ТЗ §15).
 *
 * HSTS — год и только по HTTPS: браузер, один раз увидев его, сам открывает сайт по
 * HTTPS и не даёт подменить страницу в чужой сети. Поддомены не затрагиваем: на домене
 * живёт почта и тестовый сайт. Permissions-Policy отключает устройства, которые магазину
 * не нужны, — даже если на страницу попадёт чужой скрипт, камера и геолокация ему недоступны.
 */
final class AddSecurityHeaders
{
    public const string HSTS = 'max-age=31536000';

    public const string PERMISSIONS = 'camera=(), microphone=(), geolocation=(), payment=(), usb=()';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', self::HSTS);
        }

        $response->headers->set('Permissions-Policy', self::PERMISSIONS);

        return $response;
    }
}
