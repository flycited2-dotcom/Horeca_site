<?php

namespace App\Enums;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    public const string TRANSLATION_KEY = 'order_status';

    case New = 'new';
    case Processing = 'processing';
    case Confirmed = 'confirmed';
    case Invoiced = 'invoiced';
    case Paid = 'paid';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case Canceled = 'canceled';

    /**
     * The badge in the admin (TZ §12): a new order stands out until someone takes it.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::New => 'danger',
            self::Processing, self::Confirmed => 'warning',
            self::Invoiced => 'info',
            self::Paid, self::Shipped => 'success',
            self::Completed, self::Canceled => 'gray',
        };
    }

    /**
     * The statuses the customer is told about by e-mail (TZ §13); new and processing are internal.
     */
    public function isToldToCustomer(): bool
    {
        return ! in_array($this, [self::New, self::Processing], true);
    }
}
