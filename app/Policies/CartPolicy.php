<?php

namespace App\Policies;

use App\Models\Cart;
use App\Models\User;

/**
 * Covers carts of signed-in users; a guest cart is bound to the session by the cart actions.
 */
class CartPolicy
{
    public function view(User $user, Cart $cart): bool
    {
        return $this->owns($user, $cart);
    }

    public function update(User $user, Cart $cart): bool
    {
        return $this->owns($user, $cart);
    }

    public function delete(User $user, Cart $cart): bool
    {
        return $this->owns($user, $cart);
    }

    private function owns(User $user, Cart $cart): bool
    {
        return $cart->user_id !== null && $cart->user_id === $user->id;
    }
}
