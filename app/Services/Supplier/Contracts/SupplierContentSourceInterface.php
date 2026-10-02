<?php

namespace App\Services\Supplier\Contracts;

use App\Services\Supplier\Data\SupplierContentPage;
use App\Services\Supplier\Exceptions\FeedReadException;

/**
 * Where supplier product content comes from (TZ §6): the product list of the supplier's site
 * or the supplier API, whichever the config picks. Everything after the adapter works with the
 * DTOs only.
 */
interface SupplierContentSourceInterface
{
    /**
     * Content of one page of products, pages count from 1. A source that walks its list by
     * a cursor (the API) continues from $cursor, the cursor the previous page returned; a source
     * that counts pages (the site) ignores it.
     *
     * @throws FeedReadException
     */
    public function page(int $page, ?string $cursor = null): SupplierContentPage;

    /**
     * Downloads one photo and returns its bytes.
     *
     * @throws FeedReadException
     */
    public function download(string $url): string;
}
