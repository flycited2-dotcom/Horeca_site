<?php

namespace App\Observers;

use App\Models\PriceTier;

/**
 * Группа по умолчанию одна (ТЗ §5: price_tiers.is_default): её предлагают первой при
 * одобрении компании. Отметили другую — с прежней отметка снимается.
 */
final class PriceTierObserver
{
    public function saved(PriceTier $tier): void
    {
        if (! $tier->is_default || ! ($tier->wasRecentlyCreated || $tier->wasChanged('is_default'))) {
            return;
        }

        PriceTier::query()
            ->whereKeyNot($tier->getKey())
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
