<?php

namespace App\Enums;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CompanyStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    public const string TRANSLATION_KEY = 'company_status';

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Blocked = 'blocked';

    /**
     * The badge in the admin (TZ §12): a company waiting for moderation stands out.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'gray',
            self::Blocked => 'danger',
        };
    }
}
