<?php

namespace App\Enums;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    use HasTranslatedLabel;

    public const string TRANSLATION_KEY = 'user_role';

    case Customer = 'customer';
    case Manager = 'manager';
    case Admin = 'admin';

    public function isStaff(): bool
    {
        return $this === self::Manager || $this === self::Admin;
    }
}
