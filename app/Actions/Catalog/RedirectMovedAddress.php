<?php

namespace App\Actions\Catalog;

use App\Models\Redirect;

/**
 * Keeps an old storefront address working after the manager renames it (TZ §6.6).
 *
 * The old address gets a 301 to the new one, redirects that pointed at the old address are
 * moved to the new one so that search engines never walk a chain, and a redirect that would
 * now loop (the address was renamed back) is dropped.
 */
final class RedirectMovedAddress
{
    public function handle(string $from, string $to): void
    {
        if ($from === $to) {
            return;
        }

        Redirect::query()->where('from_path', $to)->delete();

        Redirect::query()->where('to_path', $from)->update(['to_path' => $to]);

        Redirect::query()->updateOrCreate(
            ['from_path' => $from],
            ['to_path' => $to, 'status_code' => 301],
        );
    }
}
