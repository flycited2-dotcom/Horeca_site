<?php

namespace App\View;

use App\Http\Controllers\CookieConsentController;
use App\Models\Page;
use Illuminate\Support\Str;

/**
 * What the storefront layout shows around every page (TZ §8.1, layout — screen 5):
 * contacts and messengers from the settings, root categories, the pages the manager has switched on and
 * how many models the customer compares and keeps in favorites, what is in the cart.
 */
final readonly class StorefrontShell
{
    /**
     * @param  list<array{label: string, href: string}>  $phones
     * @param  list<array{id: int, name: string, slug: string, icon: ?string, show_on_home: bool, products_count: int}>  $categories
     * @param  list<Page>  $stripPages
     * @param  list<Page>  $footerPages
     * @param  list<array{key: string, label: string, href: string}>  $messengers
     */
    public function __construct(
        public string $siteName,
        public array $phones,
        public ?string $email,
        public ?string $schedule,
        public ?string $address,
        public ?string $requisites,
        public array $categories,
        public ?int $currentRootId,
        public bool $inCatalog,
        public array $stripPages,
        public array $footerPages,
        public ?Page $privacyPage,
        public int $compareCount = 0,
        public CartHeadline $cart = new CartHeadline,
        public ?string $customerName = null,
        public ?string $customerEmail = null,
        public int $favoritesCount = 0,
        public ?string $metrikaId = null,
        public ?string $cookieConsent = null,
        public array $messengers = [],
    ) {}

    /**
     * Yandex Metrica runs only with the visitor's consent to analytics cookies (TZ §15.10).
     */
    public function analyticsAllowed(): bool
    {
        return $this->metrikaId !== null && $this->cookieConsent === CookieConsentController::ALL;
    }

    /**
     * Where a service page leads: the wholesale page lives on its own route with the application
     * form (TZ §11), every other page — at its slug.
     */
    public function pageUrl(Page $page): string
    {
        return $page->url();
    }

    /**
     * The header tile has room for one word: the first name of the signed-in customer.
     */
    public function customerFirstName(): ?string
    {
        if ($this->customerName === null) {
            return null;
        }

        return Str::before(trim($this->customerName), ' ');
    }

    /**
     * @return array{label: string, href: string}|null
     */
    public function phone(): ?array
    {
        return $this->phones[0] ?? null;
    }

    /**
     * The main sections the manager marked: they are the plates of the bar under the header
     * and the list in the footer; without marks — the first eight. The rest wait under «Ещё».
     *
     * @return list<array{id: int, name: string, slug: string, icon: ?string, show_on_home: bool, products_count: int}>
     */
    public function featuredCategories(): array
    {
        $featured = array_values(array_filter($this->categories, fn (array $category): bool => $category['show_on_home']));

        return $featured !== [] ? $featured : array_slice($this->categories, 0, 8);
    }

    /**
     * The sections that are not main ones: only the «Ещё» of the bar lists them.
     *
     * @return list<array{id: int, name: string, slug: string, icon: ?string, show_on_home: bool, products_count: int}>
     */
    public function otherCategories(): array
    {
        $featured = array_column($this->featuredCategories(), 'id');

        return array_values(array_filter($this->categories, fn (array $category): bool => ! in_array($category['id'], $featured, true)));
    }

    /**
     * @return list<array{id: int, name: string, slug: string, icon: ?string, show_on_home: bool, products_count: int}>
     */
    public function footerCategories(): array
    {
        return $this->featuredCategories();
    }
}
