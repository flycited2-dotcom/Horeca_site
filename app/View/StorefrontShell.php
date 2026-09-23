<?php

namespace App\View;

use App\Models\Page;
use Illuminate\Support\Str;

/**
 * What the storefront layout shows around every page (TZ §8.1, layout — screen 5):
 * contacts from the settings, root categories, the pages the manager has switched on and
 * how many models the customer compares, what is in the cart.
 */
final readonly class StorefrontShell
{
    /**
     * @param  list<array{label: string, href: string}>  $phones
     * @param  list<array{id: int, name: string, slug: string, icon: ?string, show_on_home: bool, products_count: int}>  $categories
     * @param  list<Page>  $stripPages
     * @param  list<Page>  $footerPages
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
    ) {}

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
     * The footer lists the sections marked for the home page; without such marks — the first eight.
     *
     * @return list<array{id: int, name: string, slug: string, icon: ?string, show_on_home: bool, products_count: int}>
     */
    public function footerCategories(): array
    {
        $featured = array_values(array_filter($this->categories, fn (array $category): bool => $category['show_on_home']));

        return $featured !== [] ? $featured : array_slice($this->categories, 0, 8);
    }
}
