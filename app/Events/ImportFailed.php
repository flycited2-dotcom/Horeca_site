<?php

namespace App\Events;

use App\Models\ImportRun;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A run could not be read or did not pass a threshold, so the catalog was left alone.
 * Managers hear about it at once (TZ §13).
 */
final class ImportFailed
{
    use Dispatchable;

    public function __construct(
        public readonly ImportRun $run,
        public readonly string $reason,
    ) {}
}
