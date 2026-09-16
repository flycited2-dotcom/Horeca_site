<?php

namespace App\Enums;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum OrderType: string implements HasLabel
{
    use HasTranslatedLabel;

    public const string TRANSLATION_KEY = 'order_type';

    case Retail = 'retail';
    case Wholesale = 'wholesale';
}
