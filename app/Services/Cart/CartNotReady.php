<?php

namespace App\Services\Cart;

use DomainException;

/**
 * The cart can not become an order right now: it is empty, or a line in it can no longer be
 * bought and must be removed first (TZ §10.1).
 */
final class CartNotReady extends DomainException
{
    public function __construct(public readonly bool $empty)
    {
        parent::__construct(__($empty ? 'shop.checkout.cart_empty' : 'shop.cart.blocked_checkout'));
    }
}
