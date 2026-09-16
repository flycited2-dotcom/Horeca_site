<?php

namespace App\Enums;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum WarehouseStockStatus: string implements HasLabel
{
    use HasTranslatedLabel;

    public const string TRANSLATION_KEY = 'warehouse_stock_status';

    case InStock = 'in_stock';
    case Low = 'low';
    case Out = 'out';
}
