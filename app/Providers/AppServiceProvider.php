<?php

namespace App\Providers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\Warehouse;
use App\Observers\CatalogCacheObserver;
use App\Observers\PriceTierObserver;
use App\Observers\SlugRedirectObserver;
use App\Services\Catalog\CategoryTree;
use App\Services\Compare\CompareList;
use App\Services\Favorites\FavoriteList;
use App\Services\Search\DatabaseSearchEngine;
use App\Services\Search\SearchEngineInterface;
use App\Services\Settings\Settings;
use App\Services\Supplier\Contracts\SupplierPhotoSourceInterface;
use App\Services\Supplier\Sources\Rosholod\RosholodSitePhotoSource;
use App\View\Composers\StorefrontLayoutComposer;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\NotPwnedVerifier;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CategoryTree::class);
        $this->app->scoped(Settings::class);
        $this->app->scoped(CompareList::class);
        $this->app->scoped(FavoriteList::class);
        $this->app->bind(SearchEngineInterface::class, DatabaseSearchEngine::class);
        // Фото поставщика — со списка товаров его сайта, пока нет API (ТЗ §6); API заменит адаптер здесь.
        $this->app->bind(SupplierPhotoSourceInterface::class, RosholodSitePhotoSource::class);

        // Проверка пароля по базе утечек (ТЗ §15.2) ждёт ответа не дольше 5 секунд: если
        // сервис не ответит, регистрация не должна висеть полминуты. Без ответа пароль принимается.
        $this->app->extend(UncompromisedVerifier::class, fn (UncompromisedVerifier $verifier, Application $app): UncompromisedVerifier => new NotPwnedVerifier($app->make(HttpFactory::class), 5));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Listeners of app/Listeners are found by event discovery: registering them here too would call them twice.

        Product::observe(SlugRedirectObserver::class);
        Category::observe([SlugRedirectObserver::class, CatalogCacheObserver::class]);
        Brand::observe([SlugRedirectObserver::class, CatalogCacheObserver::class]);
        Page::observe(SlugRedirectObserver::class);
        Warehouse::observe(CatalogCacheObserver::class);
        PriceTier::observe(PriceTierObserver::class);

        View::composer('components.layouts.app', StorefrontLayoutComposer::class);

        // Пароли клиентов и сотрудников (ТЗ §15.2): не короче 8 символов и не из известных утечек.
        Password::defaults(fn (): Password => Password::min(8)->uncompromised());
    }
}
