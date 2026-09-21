<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Compare\CompareList;
use Illuminate\Auth\Events\Login;

/**
 * Модели, которые гость сравнивал до входа, остаются в сравнении после входа (ТЗ §8.5).
 */
final class MergeGuestComparison
{
    public function __construct(private readonly CompareList $compare) {}

    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            $this->compare->mergeGuestList($event->user);
        }
    }
}
