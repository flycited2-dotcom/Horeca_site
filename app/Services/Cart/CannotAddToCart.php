<?php

namespace App\Services\Cart;

use DomainException;

/**
 * The product can not go into the cart (TZ §10.1): the storefront shows no button for it,
 * and a request that still tries gets this reason back.
 */
final class CannotAddToCart extends DomainException
{
    public function __construct(public readonly CartBlock $block)
    {
        parent::__construct($block->message());
    }
}
