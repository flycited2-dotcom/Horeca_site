<?php

namespace App\Services\Supplier\Contracts;

use App\Models\ImportProfile;
use App\Services\Supplier\Data\FeedCapabilities;
use App\Services\Supplier\Data\FetchedSource;
use App\Services\Supplier\Data\SupplierCategory;
use App\Services\Supplier\Data\SupplierProduct;
use App\Services\Supplier\Data\SupplierProductStock;
use App\Services\Supplier\Data\SupplierRecordError;
use App\Services\Supplier\Exceptions\FeedReadException;

/**
 * A supplier data source: an XML feed today, the supplier API later (TZ §6.2).
 *
 * Everything after the adapter works with the DTOs only and never knows the source.
 */
interface SupplierFeedInterface
{
    /**
     * Which entities the source provides, which product fields it owns and whether it is a full snapshot.
     */
    public function capabilities(): FeedCapabilities;

    /**
     * Downloads the data. Returns null when the source has not changed (HTTP 304) and $force is false.
     *
     * @throws FeedReadException
     */
    public function fetch(ImportProfile $profile, bool $force = false): ?FetchedSource;

    /**
     * Streams the downloaded data as DTOs. Broken records are yielded as SupplierRecordError.
     *
     * @return iterable<int, SupplierCategory|SupplierProduct|SupplierProductStock|SupplierRecordError>
     *
     * @throws FeedReadException
     */
    public function read(FetchedSource $source): iterable;
}
