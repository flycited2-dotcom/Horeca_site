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
 */
final class SlugRedirectObserver
{
    public function __construct(private readonly RedirectMovedAddress $redirects) {}

    public function updated(Model $model): void
    {
        if (! $model->wasChanged('slug')) {
            return;
        }

        $prefix = match (true) {
            $model instanceof Product => StorefrontPaths::PRODUCT,
            $model instanceof Category => StorefrontPaths::CATEGORY,
            $model instanceof Brand => StorefrontPaths::BRAND,
            default => null,
        };

        if ($prefix === null) {
            return;
        }

        $this->redirects->handle($prefix.$model->getOriginal('slug'), $prefix.$model->getAttribute('slug'));
    }
}
