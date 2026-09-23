<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Favorites\FavoriteList;
use Illuminate\Auth\Events\Login;

/**
 * Что гость отметил в избранном до входа, остаётся в избранном после входа (ТЗ §5).
 */
final class MergeGuestFavorites
{
    public function __construct(private readonly FavoriteList $favorites) {}

    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            $this->favorites->mergeGuestList($event->user);
        }
    }
}
