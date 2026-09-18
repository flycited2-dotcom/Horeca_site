<?php

namespace App\Services\Catalog;

/**
 * Sort orders of a listing (TZ §8.2). Popularity is the default.
 */
enum CatalogSort: string
{
    case Popular = 'popular';
    case PriceAsc = 'price_asc';
    case PriceDesc = 'price_desc';
    case Newest = 'new';
    case Name = 'name';

    public function label(): string
    {
        return __("shop.catalog.sort.{$this->value}");
    }
}
