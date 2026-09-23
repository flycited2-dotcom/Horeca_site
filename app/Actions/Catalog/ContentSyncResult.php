<?php

namespace App\Actions\Catalog;

/**
 * What happened to the content of one supplier product: its photos or its details.
 */
enum ContentSyncResult
{
    /** Photos were replaced, or a description, dimension or characteristic changed. */
    case Updated;

    /** The supplier gives the same as last time: nothing downloaded or written. */
    case Unchanged;

    /** There is no such product of this supplier in the catalog. */
    case NoProduct;
}
