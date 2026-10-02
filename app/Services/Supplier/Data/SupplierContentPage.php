<?php

namespace App\Services\Supplier\Data;

/**
 * One page of a supplier's product content — photos and details — and how many pages there are.
 * A source with a cursor does not know the number of pages: while it has more, $lastPage is the
 * next page, and $nextCursor says where to continue.
 */
final readonly class SupplierContentPage
{
    /**
     * @param  list<SupplierProductPhotos>  $products  photos of the products of the page
     * @param  list<SupplierProductDetails>  $details  descriptions, dimensions and characteristics
     */
    public function __construct(
        public int $page,
        public int $lastPage,
        public array $products,
        public array $details = [],
        public ?string $nextCursor = null,
    ) {}

    public function isLast(): bool
    {
        return $this->page >= $this->lastPage;
    }
}
