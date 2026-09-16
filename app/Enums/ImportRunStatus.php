<?php

namespace App\Enums;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum ImportRunStatus: string implements HasLabel
{
    use HasTranslatedLabel;

    public const string TRANSLATION_KEY = 'import_run_status';

    case Queued = 'queued';
    case Running = 'running';
    case Success = 'success';
    case Skipped = 'skipped';
    case Failed = 'failed';
}
