<?php

namespace App\Services\Cart;

/**
 * Why a product can not be bought from the cart (TZ §10.1): it has no price, it is no longer
 * made, or the manager has taken it off the storefront.
 */
enum CartBlock: string
{
    case Hidden = 'hidden';
    case Discontinued = 'discontinued';
    case PriceOnRequest = 'price_on_request';

    /**
     * Why the product was not put into the cart.
     */
    public function message(): string
    {
        return __('shop.cart.cannot_add.'.$this->value);
    }

    /**
     * What to do with a line that can no longer be bought.
     */
    public function lineMessage(): string
    {
        return __('shop.cart.blocked.'.$this->value);
    }
}
