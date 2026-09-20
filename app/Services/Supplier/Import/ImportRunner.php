<?php

namespace App\Services\Supplier\Import;

use App\Enums\ImportRunStatus;
use App\Enums\ImportTrigger;
use App\Events\ImportFailed;
use App\Events\ImportFinished;
use App\Models\ImportProfile;
use App\Models\ImportRow;
use App\Models\ImportRun;
use App\Services\Catalog\CatalogCache;
use App\Services\Supplier\Data\FeedCapabilities;
use App\Services\Supplier\Data\FetchedSource;
use App\Services\Supplier\Exceptions\FeedReadException;
use App\Services\Supplier\Exceptions\ImportAlreadyRunningException;
use App\Services\Supplier\Exceptions\ImportRejectedException;
use App\Services\Supplier\Sync\CatalogApplier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * One import run from end to end (TZ §6.3).
 *
 * Nothing reaches the catalog until the whole source has been read into staging and has
 * passed the thresholds, and the catalog is changed in a single transaction. A dry run is
 * that same transaction rolled back, so its numbers are the real ones.
 */
final class ImportRunner
{
    public function __construct(
        private readonly SourceRegistry $sources,
        private readonly StagingWriter $staging,
        private readonly ThresholdGuard $thresholds,
        private readonly CatalogApplier $applier,
        private readonly CatalogCache $cache,
    ) {}

    /**
     * @throws ImportAlreadyRunningException when another run of this supplier is in progress
     * @throws Throwable on an unexpected failure; expected ones are recorded in the run
     */
    public function run(
        ImportProfile $profile,
        ImportTrigger $trigger,
        bool $force = false,
        bool $dryRun = false,
        ?int $userId = null,
    ): ImportRun {
        $profile->loadMissing('supplier');

        $lock = Cache::lock('import:supplier:'.$profile->supplier_id, (int) config('import.lock_seconds'));

        if (! $lock->get()) {
            throw new ImportAlreadyRunningException($profile->supplier);
        }

        // Everything below is inside the lock: a failure before the run row exists would
        // otherwise leave the supplier locked until the lock expires two hours later.
        $run = null;
        $log = null;

        try {
            $run = ImportRun::query()->create([
                'import_profile_id' => $profile->id,
                'user_id' => $userId,
                'trigger' => $trigger,
                'status' => ImportRunStatus::Running,
                'is_dry_run' => $dryRun,
                'started_at' => now(),
            ]);

            $log = new ImportLog($run->id, (int) config('import.log_limit'));

            $this->process($profile, $run, $log, $force, $dryRun);
        } catch (FeedReadException|ImportRejectedException $exception) {
            $this->fail($run, $log, $exception->getMessage());
        } catch (Throwable $exception) {
            $this->fail($run, $log, $exception->getMessage());

            throw $exception;
        } finally {
            $log?->close();

            if ($run !== null) {
                ImportRow::query()->where('import_run_id', $run->id)->delete();
            }

            $lock->release();
        }

        return $run;
    }

    /**
     * @throws FeedReadException
     * @throws ImportRejectedException
     */
    private function process(ImportProfile $profile, ImportRun $run, ImportLog $log, bool $force, bool $dryRun): void
    {
        $source = $this->sources->for($profile);
        $capabilities = $source->capabilities();
        $fetched = $source->fetch($profile, $force);

        if ($fetched === null) {
            $this->skip($run, __('import.messages.not_modified'));

            return;
        }

        $run->forceFill([
            'file_path' => $fetched->relativePath,
            'file_hash' => $fetched->hash,
            'source_version' => $fetched->etag,
        ])->save();

        if (! $force && $this->isSameFile($profile, $fetched)) {
            $this->skip($run, __('import.messages.same_file'));

            return;
        }

        $stats = $this->staging->write($run, $source->read($fetched), $log);

        $run->forceFill([
            'rows_total' => $stats->total(),
            'errors' => $stats->invalid(),
        ])->save();

        $this->thresholds->check($profile, $capabilities, $stats);

        $counters = $dryRun
            ? $this->applyAndRollBack($run, $profile, $capabilities, $stats, $log)
            : DB::transaction(fn (): ImportCounters => $this->applier->apply($run, $profile, $capabilities, $stats, $log));

        $run->forceFill($counters->toArray() + [
            'status' => ImportRunStatus::Success,
            'log' => $log->messages(),
            'log_file' => $log->relativePath(),
            'finished_at' => now(),
        ])->save();

        if ($dryRun) {
            return;
        }

        // The conditional GET markers are stored only after a run that worked: a failed
        // run must read the same file again instead of being answered with 304.
        $profile->forceFill([
            'last_etag' => $fetched->etag,
            'last_modified' => $fetched->lastModified,
        ])->save();

        $this->cache->bump();

        event(new ImportFinished($run));
    }

    /**
     * Does the real work and undoes it: the report shows what would have happened.
     */
    private function applyAndRollBack(
        ImportRun $run,
        ImportProfile $profile,
        FeedCapabilities $capabilities,
        StagingStats $stats,
        ImportLog $log,
    ): ImportCounters {
        DB::beginTransaction();

        try {
            return $this->applier->apply($run, $profile, $capabilities, $stats, $log);
        } finally {
            DB::rollBack();
        }
    }

    /**
     * The supplier re-uploads the feeds several times a day and the ETag changes even when
     * the content does not, so the file is compared by its hash as well (TZ §6.1, fact 14).
     */
    private function isSameFile(ImportProfile $profile, FetchedSource $fetched): bool
    {
        return ImportRun::query()
            ->where('import_profile_id', $profile->id)
            ->where('status', ImportRunStatus::Success)
            ->where('is_dry_run', false)
            ->where('file_hash', $fetched->hash)
            ->exists();
    }

    private function skip(ImportRun $run, string $reason): void
    {
        $run->forceFill([
            'status' => ImportRunStatus::Skipped,
            'log' => [$reason],
            'finished_at' => now(),
        ])->save();
    }

    private function fail(?ImportRun $run, ?ImportLog $log, string $message): void
    {
        if ($run === null) {
            return;
        }

        $run->forceFill([
            'status' => ImportRunStatus::Failed,
            'error_message' => $message,
            'log' => $log?->messages() ?? [],
            'log_file' => $log?->relativePath(),
            'finished_at' => now(),
        ])->save();

        event(new ImportFailed($run, $message));
    }
}
