<?php

namespace App\View\Composers;

use App\Http\Controllers\CookieConsentController;
use App\Http\Controllers\WholesaleController;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Services\Cart\CartReview;
use App\Services\Catalog\CatalogQuery;
use App\Services\Catalog\CategoryTree;
use App\Services\Compare\CompareList;
use App\Services\Favorites\FavoriteList;
use App\Services\Settings\Settings;
use App\Support\Phone;
use App\View\StorefrontShell;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Collects the storefront shell once per page: the layout and its header and footer only
 * display it. Settings come with one query, root categories from the catalog cache.
 */
final class StorefrontLayoutComposer
{
    /**
     * Pages linked from the service strip and the menu, in this order. A page appears only
     * when the manager has switched it on: until then it has no text.
     */
    public const array STRIP_PAGES = ['dostavka', 'optovikam', 'o-kompanii', 'kontakty'];

    public const array FOOTER_PAGES = ['dostavka', 'oplata', 'garantiya', 'optovikam', 'kontakty'];

    public const string PRIVACY_PAGE = 'politika-konfidencialnosti';

    public function __construct(
        private readonly Settings $settings,
        private readonly CatalogQuery $catalog,
        private readonly CategoryTree $tree,
        private readonly Request $request,
        private readonly CompareList $compare,
        private readonly CartReview $cart,
        private readonly FavoriteList $favorites,
    ) {}

    public function compose(View $view): void
    {
        $this->settings->preload('site.name', 'contacts.phones', 'contacts.email', 'contacts.schedule', 'contacts.address', 'seller.requisites', 'analytics.metrika_id');
        $consent = $this->request->cookie(CookieConsentController::COOKIE);

        $pages = Page::query()
            ->where('is_active', true)
            ->whereIn('slug', [...self::STRIP_PAGES, ...self::FOOTER_PAGES, self::PRIVACY_PAGE])
            ->get(['slug', 'title'])
            ->keyBy('slug');

        // «Оптовым клиентам» есть всегда: там заявка на опт (ТЗ §11). Текст страницы
        // «Оптовикам» — выгоды от заказчика — только дополняет её, когда администратор его включит.
        if (! $pages->has(WholesaleController::BENEFITS_PAGE)) {
            $pages->put(WholesaleController::BENEFITS_PAGE, (new Page)->forceFill([
                'slug' => WholesaleController::BENEFITS_PAGE,
                'title' => __('shop.wholesale.title'),
            ]));
        }

        $view->with('shell', new StorefrontShell(
            siteName: $this->text('site.name') ?? (string) config('app.name'),
            phones: Phone::links($this->settings->get('contacts.phones')),
            email: $this->text('contacts.email'),
            schedule: $this->text('contacts.schedule'),
            address: $this->text('contacts.address'),
            requisites: $this->text('seller.requisites'),
            categories: $this->catalog->navigationCategories(),
            currentRootId: $this->currentRootId(),
            inCatalog: $this->request->routeIs('catalog'),
            stripPages: array_values(array_filter(array_map(fn (string $slug): ?Page => $pages->get($slug), self::STRIP_PAGES))),
            footerPages: array_values(array_filter(array_map(fn (string $slug): ?Page => $pages->get($slug), self::FOOTER_PAGES))),
            privacyPage: $pages->get(self::PRIVACY_PAGE),
            compareCount: $this->compare->count($this->request->user()),
            cart: $this->cart->headline($this->request->user()),
            customerName: $this->request->user()?->name,
            customerEmail: $this->request->user()?->email,
            favoritesCount: $this->favorites->count($this->request->user()),
            metrikaId: preg_match('/^\d{5,12}$/', (string) $this->text('analytics.metrika_id')) === 1 ? $this->text('analytics.metrika_id') : null,
            cookieConsent: in_array($consent, [CookieConsentController::ALL, CookieConsentController::NECESSARY], true) ? $consent : null,
        ));
    }

    private function text(string $key): ?string
    {
        $value = $this->settings->get($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * The root section of the category or product on screen, so the header can mark it.
     */
    private function currentRootId(): ?int
    {
        $route = $this->request->route();
        $category = $route?->parameter('category');
        $product = $route?->parameter('product');

        $categoryId = match (true) {
            $category instanceof Category => $category->id,
            $product instanceof Product => $product->category_id,
            default => null,
        };

        return $categoryId !== null ? $this->tree->rootOf($categoryId) : null;
    }
}
