<?php

namespace App\Listeners;

use App\Actions\Cart\MergeGuestCart;
use App\Models\User;
use App\Services\Cart\CartStore;
use Illuminate\Auth\Events\Login;

/**
 * Корзина, собранная до входа, не теряется после входа (ТЗ §10.1).
 */
final class MergeGuestCartOnLogin
{
    public function __construct(
        private readonly CartStore $carts,
        private readonly MergeGuestCart $merge,
    ) {}

    public function handle(Login $event): void
    {
        $guest = $this->carts->guest();

        if ($guest !== null && $event->user instanceof User) {
            $this->merge->handle($guest, $event->user);
        }
    }
}
