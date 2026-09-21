<?php

namespace App\Services\Compare;

/**
 * What happened to a product the customer put into the comparison or took out of it.
 */
enum CompareResult
{
    case Added;
    case Removed;
    /** The list already holds CompareList::LIMIT models: nothing was added. */
    case Full;
}
