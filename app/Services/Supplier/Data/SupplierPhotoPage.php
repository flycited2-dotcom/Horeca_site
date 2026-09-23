<?php

namespace App\Services\Supplier\Data;

/**
 * One page of product photos from a supplier source and how many pages there are.
 */
final readonly class SupplierPhotoPage
{
    /**
     * @param  list<SupplierProductPhotos>  $products
     */
    public function __construct(
        public int $page,
        public int $lastPage,
        public array $products,
    ) {}

    public function isLast(): bool
    {
        return $this->page >= $this->lastPage;
    }
}
