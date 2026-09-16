<?php

namespace App\Enums;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum PriceKind: string implements HasLabel
{
    use HasTranslatedLabel;

    public const string TRANSLATION_KEY = 'price_kind';

    case Rrp = 'rrp';
    case Dealer = 'dealer';
}
