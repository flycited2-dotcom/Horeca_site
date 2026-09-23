<?php

namespace App\Events;

use App\Models\Company;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A customer has sent a wholesale application (TZ §11): the managers are told in Telegram and
 * by e-mail, the customer gets a confirmation (§13). Dispatched after the transaction commits.
 * A recheck — an approved or rejected company changed its INN or legal name in the account:
 * the managers check it again, the customer has already seen the result on the page.
 */
final class WholesaleApplied
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Company $company,
        public readonly bool $recheck = false,
    ) {}
}
