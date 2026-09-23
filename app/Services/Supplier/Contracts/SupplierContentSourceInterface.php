<?php

namespace App\Services\Supplier\Contracts;

use App\Services\Supplier\Data\SupplierContentPage;
use App\Services\Supplier\Exceptions\FeedReadException;

/**
 * Where supplier product photos come from (TZ §6): the product list of the supplier's site
 * today, the supplier API later. Everything after the adapter works with the DTOs only.
 */
interface SupplierContentSourceInterface
{
    /**
     * Photos of one page of products, pages count from 1.
     *
     * @throws FeedReadException
     */
    public function page(int $page): SupplierContentPage;

    /**
     * Downloads one photo and returns its bytes.
     *
     * @throws FeedReadException
     */
    public function download(string $url): string;
}
