<?php

namespace App\Enums;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum CompanyStatus: string implements HasLabel
{
    use HasTranslatedLabel;

    public const string TRANSLATION_KEY = 'company_status';

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Blocked = 'blocked';
}
