<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RedirectStorefrontOrigin
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = config('seo.origin');
        if ($origin && parse_url($origin, PHP_URL_SCHEME) === 'https') {
            $canonicalHost = parse_url($origin, PHP_URL_HOST);
            $host = $request->getHost();

            if ($host === 'www.'.$canonicalHost || ($host === $canonicalHost && ! $request->isSecure())) {
                return redirect()->away(rtrim($origin, '/').$request->getRequestUri(), 301);
            }
        }

        return $next($request);
    }
}
