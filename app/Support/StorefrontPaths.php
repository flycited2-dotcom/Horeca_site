<?php

namespace App\Support;

/**
 * Storefront addresses of catalog records (TZ §8). One place, so that redirects written
 * today match the routes of the storefront.
 */
final class StorefrontPaths
{
    public const string PRODUCT = '/product/';

    public const string CATEGORY = '/catalog/';

    public const string BRAND = '/brands/';

    /**
     * Pages of the CMS live at the root: /dostavka (TZ §8, Route::fallback).
     */
    public const string PAGE = '/';

    public static function product(string $slug): string
    {
        return self::PRODUCT.$slug;
    }

    public static function category(string $slug): string
    {
        return self::CATEGORY.$slug;
    }

    public static function brand(string $slug): string
    {
        return self::BRAND.$slug;
    }
}
