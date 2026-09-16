<?php

namespace App\Enums;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    use HasTranslatedLabel;

    public const string TRANSLATION_KEY = 'payment_method';

    case Invoice = 'invoice';
    case Cash = 'cash';
    case CardOnDelivery = 'card_on_delivery';
    case Online = 'online';
}
