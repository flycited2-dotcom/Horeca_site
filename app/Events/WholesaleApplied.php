<?php

namespace App\Events;

use App\Models\Company;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A customer has sent a wholesale application (TZ §11): the managers are told in Telegram and
 * by e-mail, the customer gets a confirmation (§13). Dispatched after the transaction commits.
 */
final class WholesaleApplied
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Company $company) {}
}
