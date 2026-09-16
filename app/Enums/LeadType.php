<?php

namespace App\Enums;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum LeadType: string implements HasLabel
{
    use HasTranslatedLabel;

    public const string TRANSLATION_KEY = 'lead_type';

    case Callback = 'callback';
    case Question = 'question';
    case PriceRequest = 'price_request';
    case AvailabilityRequest = 'availability_request';
    case AnalogRequest = 'analog_request';
    case OneClick = 'one_click';
    case NotFound = 'not_found';
}
