<?php

namespace App\Enums;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum DeliveryMethod: string implements HasLabel
{
    use HasTranslatedLabel;

    public const string TRANSLATION_KEY = 'delivery_method';

    case Pickup = 'pickup';
    case TransportCompany = 'transport_company';
    case CourierCity = 'courier_city';
}
