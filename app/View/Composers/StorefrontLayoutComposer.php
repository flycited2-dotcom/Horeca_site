<?php

namespace App\View\Composers;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Services\Cart\CartReview;
use App\Services\Catalog\CatalogQuery;
use App\Services\Catalog\CategoryTree;
use App\Services\Compare\CompareList;
use App\Services\Settings\Settings;
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
    ) {}

    public function compose(View $view): void
    {
        $this->settings->preload('site.name', 'contacts.phones', 'contacts.email', 'contacts.schedule', 'contacts.address', 'seller.requisites');

        $pages = Page::query()
            ->where('is_active', true)
            ->whereIn('slug', [...self::STRIP_PAGES, ...self::FOOTER_PAGES, self::PRIVACY_PAGE])
            ->get(['slug', 'title'])
            ->keyBy('slug');

        $view->with('shell', new StorefrontShell(
            siteName: $this->text('site.name') ?? (string) config('app.name'),
            phones: $this->phones(),
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
        ));
    }

    private function text(string $key): ?string
    {
        $value = $this->settings->get($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * The customer may enter one phone, several separated by commas, or a list.
     *
     * @return list<array{label: string, href: string}>
     */
    private function phones(): array
    {
        $value = $this->settings->get('contacts.phones');
        $phones = is_array($value) ? $value : preg_split('/[,;\n]+/', is_string($value) ? $value : '');

        $result = [];

        foreach ($phones as $phone) {
            if (is_string($phone) && trim($phone) !== '') {
                $result[] = ['label' => trim($phone), 'href' => 'tel:'.preg_replace('/[^+\d]/', '', $phone)];
            }
        }

        return $result;
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
