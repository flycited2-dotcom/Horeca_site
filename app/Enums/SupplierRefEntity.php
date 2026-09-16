<?php

namespace App\Enums;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum SupplierRefEntity: string implements HasLabel
{
    use HasTranslatedLabel;

    public const string TRANSLATION_KEY = 'supplier_ref_entity';

    case Category = 'category';
    case Brand = 'brand';
    case Warehouse = 'warehouse';
    case Attribute = 'attribute';
}
