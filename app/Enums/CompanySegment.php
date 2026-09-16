<?php

namespace App\Enums;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum CompanySegment: string implements HasLabel
{
    use HasTranslatedLabel;

    public const string TRANSLATION_KEY = 'company_segment';

    case Restaurant = 'restaurant';
    case Cafe = 'cafe';
    case Bar = 'bar';
    case Hotel = 'hotel';
    case Canteen = 'canteen';
    case Bakery = 'bakery';
    case Production = 'production';
    case RetailChain = 'retail_chain';
    case Other = 'other';
}
