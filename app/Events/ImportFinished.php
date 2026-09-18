<?php

namespace App\Events;

use App\Models\ImportRun;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A run applied a snapshot to the catalog. Successful runs are not announced one by one:
 * the stocks are refreshed up to 35 times a day (TZ §13).
 */
final class ImportFinished
{
    use Dispatchable;

    public function __construct(public readonly ImportRun $run) {}
}
