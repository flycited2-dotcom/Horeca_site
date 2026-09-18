<?php

namespace App\Observers;

use App\Services\Catalog\CatalogCache;

/**
 * Categories, brands and warehouses shape every catalog page, so any change to them
 * invalidates the catalog cache at once (TZ §14).
 *
 * Products are not observed: the import saves thousands of them and bumps the version
 * once at the end, and the manager's edits go through actions that bump it themselves.
 */
final class CatalogCacheObserver
{
    public function __construct(private readonly CatalogCache $cache) {}

    public function saved(): void
    {
        $this->cache->bump();
    }

    public function deleted(): void
    {
        $this->cache->bump();
    }
}
