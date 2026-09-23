<?php

namespace App\Actions\Catalog;

/**
 * What happened to the photos of one supplier product.
 */
enum PhotoSyncResult
{
    /** The supplier's photos were replaced with the new ones. */
    case Updated;

    /** The supplier gives the same photos as last time: nothing downloaded. */
    case Unchanged;

    /** There is no such product of this supplier in the catalog. */
    case NoProduct;
}
