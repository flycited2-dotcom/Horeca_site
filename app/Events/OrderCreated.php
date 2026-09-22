<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A customer has sent an order (TZ §10.3): notifications hang on it now, analytics and the
 * online payment later. Dispatched after the transaction has committed.
 */
final class OrderCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Order $order) {}
}
