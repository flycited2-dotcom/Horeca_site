<?php

namespace App\Enums;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum LeadStatus: string implements HasLabel
{
    use HasTranslatedLabel;

    public const string TRANSLATION_KEY = 'lead_status';

    case New = 'new';
    case InWork = 'in_work';
    case Done = 'done';
}
