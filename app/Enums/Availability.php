<?php

namespace App\Enums;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Product availability shown on the storefront (TZ §6.5).
 */
enum Availability: string implements HasLabel
{
    use HasTranslatedLabel;

    public const string TRANSLATION_KEY = 'availability';

    case InStock = 'in_stock';
    case Low = 'low';
    case Incoming = 'incoming';
    case OnOrder = 'on_order';
    case Discontinued = 'discontinued';

    /**
     * Sort weight stored in products.availability_rank: lower goes first.
     */
    public function rank(): int
    {
        return match ($this) {
            self::InStock, self::Low => 1,
            self::Incoming => 2,
            self::OnOrder => 3,
            self::Discontinued => 4,
        };
    }
}
