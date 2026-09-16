<?php

namespace App\Enums;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum ImportTrigger: string implements HasLabel
{
    use HasTranslatedLabel;

    public const string TRANSLATION_KEY = 'import_trigger';

    case Schedule = 'schedule';
    case Manual = 'manual';
    case Cli = 'cli';
}
