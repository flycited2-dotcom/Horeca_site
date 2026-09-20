<?php

namespace App\Observers;

use App\Actions\Catalog\RedirectMovedAddress;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\StorefrontPaths;
use Illuminate\Database\Eloquent\Model;

/**
 * A manual change of a product, category or brand address creates a 301 from the old one
 * (TZ §6.6). The import never changes addresses, so only the manager triggers this.
 *
 * A new record also clears a redirect that used to lead away from its address: otherwise
 * the 301 would shadow the page that now lives there.
 */
final class SlugRedirectObserver
{
    public function __construct(private readonly RedirectMovedAddress $redirects) {}

    public function created(Model $model): void
    {
        $prefix = $this->prefix($model);

        if ($prefix === null) {
            return;
        }

        $this->redirects->free($prefix.$model->getAttribute('slug'));
    }

    public function updated(Model $model): void
    {
        $prefix = $this->prefix($model);

        if ($prefix === null || ! $model->wasChanged('slug')) {
            return;
        }

        $this->redirects->handle($prefix.$model->getOriginal('slug'), $prefix.$model->getAttribute('slug'));
    }

    private function prefix(Model $model): ?string
    {
        return match (true) {
            $model instanceof Product => StorefrontPaths::PRODUCT,
            $model instanceof Category => StorefrontPaths::CATEGORY,
            $model instanceof Brand => StorefrontPaths::BRAND,
            default => null,
        };
    }
}
