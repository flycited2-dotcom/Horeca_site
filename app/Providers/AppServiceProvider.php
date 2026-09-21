<?php

namespace App\Providers;

use App\Events\ImportFailed;
use App\Listeners\NotifyAboutFailedImport;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Warehouse;
use App\Observers\CatalogCacheObserver;
use App\Observers\SlugRedirectObserver;
use App\Services\Catalog\CategoryTree;
use App\Services\Search\DatabaseSearchEngine;
use App\Services\Search\SearchEngineInterface;
use App\Services\Settings\Settings;
use App\View\Composers\StorefrontLayoutComposer;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CategoryTree::class);
        $this->app->scoped(Settings::class);
        $this->app->bind(SearchEngineInterface::class, DatabaseSearchEngine::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(ImportFailed::class, NotifyAboutFailedImport::class);

        Product::observe(SlugRedirectObserver::class);
        Category::observe([SlugRedirectObserver::class, CatalogCacheObserver::class]);
        Brand::observe([SlugRedirectObserver::class, CatalogCacheObserver::class]);
        Warehouse::observe(CatalogCacheObserver::class);

        View::composer('components.layouts.app', StorefrontLayoutComposer::class);
    }
}
