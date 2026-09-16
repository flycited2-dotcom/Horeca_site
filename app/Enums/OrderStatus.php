<?php

namespace App\Enums;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasLabel
{
    use HasTranslatedLabel;

    public const string TRANSLATION_KEY = 'order_status';

    case New = 'new';
    case Processing = 'processing';
    case Confirmed = 'confirmed';
    case Invoiced = 'invoiced';
    case Paid = 'paid';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case Canceled = 'canceled';
}
