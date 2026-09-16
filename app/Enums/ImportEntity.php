<?php

namespace App\Enums;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum ImportEntity: string implements HasLabel
{
    use HasTranslatedLabel;

    public const string TRANSLATION_KEY = 'import_entity';

    case Category = 'category';
    case Product = 'product';
    case Stock = 'stock';
}
