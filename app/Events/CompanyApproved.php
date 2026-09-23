<?php

namespace App\Events;

use App\Models\Company;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A manager has approved a wholesale application for the first time (TZ §11): the customer
 * is told that wholesale prices are open (§13).
 */
final class CompanyApproved
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Company $company) {}
}
