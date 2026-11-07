<?php

namespace App\Support;

final class SeoUrl
{
    public static function canonical(string $url): string
    {
        $origin = config('seo.origin');

        return $origin ? rtrim($origin, '/').preg_replace('~^https?://[^/]+~i', '', $url) : $url;
    }
}
