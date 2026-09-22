<?php

namespace App\Events;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The manager has moved an order to another status (TZ §12, §13): the customer hears
 * about the statuses that matter to them.
 */
final class OrderStatusChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly OrderStatus $previous,
        public readonly ?string $comment = null,
    ) {}
}
