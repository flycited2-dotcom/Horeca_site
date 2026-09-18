<?php

namespace App\Jobs;

use App\Enums\ImportTrigger;
use App\Models\ImportProfile;
use App\Services\Supplier\Exceptions\ImportAlreadyRunningException;
use App\Services\Supplier\Import\ImportRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Runs one import profile on the "imports" queue (TZ §6.4).
 *
 * The queue has a single worker and its own connection with a retry_after longer than
 * this timeout, so a long run is never picked up twice. Order notifications go through
 * the "default" queue and are not delayed by an import.
 */
final class RunSupplierImport implements ShouldQueue
{
    use Queueable;

    /**
     * Attempts include the ones given back because the supplier was busy; a real failure
     * stops at the first exception.
     */
    public int $tries = 5;

    public int $maxExceptions = 1;

    public int $timeout;

    public function __construct(
        private readonly int $profileId,
        private readonly ImportTrigger $trigger,
        private readonly bool $force = false,
        private readonly ?int $userId = null,
    ) {
        $this->timeout = (int) config('import.job_timeout');
        $this->onConnection(config('import.queue_connection'));
        $this->onQueue(config('import.queue'));
    }

    public function handle(ImportRunner $runner): void
    {
        $profile = ImportProfile::query()->with('supplier')->find($this->profileId);

        if ($profile === null) {
            Log::warning(__('import.command.profile_not_found', ['id' => $this->profileId]));

            return;
        }

        try {
            $runner->run($profile, $this->trigger, $this->force, userId: $this->userId);
        } catch (ImportAlreadyRunningException $exception) {
            Log::info($exception->getMessage(), ['profile' => $profile->id]);

            $this->release(now()->addMinutes(5));
        }
    }
}
