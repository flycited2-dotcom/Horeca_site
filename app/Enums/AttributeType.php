<?php

namespace App\Enums;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum AttributeType: string implements HasLabel
{
    use HasTranslatedLabel;

    public const string TRANSLATION_KEY = 'attribute_type';

    case Text = 'string';
    case Number = 'number';
    case Boolean = 'bool';
}
