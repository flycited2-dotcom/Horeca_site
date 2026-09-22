<?php

namespace App\Events;

use App\Models\Lead;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A visitor has left a short request (TZ §13): the managers hear about it in Telegram.
 */
final class LeadCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Lead $lead) {}
}
