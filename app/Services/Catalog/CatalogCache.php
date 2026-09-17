<?php

namespace App\Services\Catalog;

use Illuminate\Support\Facades\Cache;

/**
 * Versioned catalog cache keys (TZ §14): bumping the version invalidates every catalog entry
 * at once and works on any cache driver, unlike cache tags.
 */
final class CatalogCache
{
    private const string VERSION_KEY = 'catalog:version';

    public function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }

    public function key(string $name): string
    {
        return "catalog:v{$this->version()}:{$name}";
    }

    public function bump(): void
    {
        Cache::add(self::VERSION_KEY, 1);
        Cache::increment(self::VERSION_KEY);
    }
}
