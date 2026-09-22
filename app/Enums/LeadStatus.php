<?php

namespace App\Enums;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LeadStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    public const string TRANSLATION_KEY = 'lead_status';

    case New = 'new';
    case InWork = 'in_work';
    case Done = 'done';

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'danger',
            self::InWork => 'warning',
            self::Done => 'gray',
        };
    }
}
