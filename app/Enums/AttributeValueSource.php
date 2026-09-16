<?php

namespace App\Enums;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum AttributeValueSource: string implements HasLabel
{
    use HasTranslatedLabel;

    public const string TRANSLATION_KEY = 'attribute_value_source';

    case Supplier = 'supplier';
    case Manual = 'manual';
}
