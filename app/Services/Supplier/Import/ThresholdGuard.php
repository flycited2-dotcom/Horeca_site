<?php

namespace App\Services\Supplier\Import;

use App\Enums\ImportRunStatus;
use App\Models\ImportProfile;
use App\Models\ImportRun;
use App\Services\Supplier\Data\FeedCapabilities;
use App\Services\Supplier\Exceptions\ImportRejectedException;

/**
 * Refuses to apply a suspicious source (TZ §6.3, step 5).
 *
 * A truncated or half-written feed looks like a valid but much smaller snapshot, so the
 * number of valid records is compared with the last successful run of the same profile.
 */
final class ThresholdGuard
{
    /**
     * @throws ImportRejectedException
     */
    public function check(ImportProfile $profile, FeedCapabilities $capabilities, StagingStats $stats): void
    {
        if ($stats->total() === 0) {
            throw new ImportRejectedException(__('import.errors.no_records'));
        }

        $this->checkInvalidShare($profile, $stats);

        if ($capabilities->isFullSnapshot) {
            $this->checkRecordCount($profile, $stats);
        }
    }

    /**
     * @throws ImportRejectedException
     */
    private function checkInvalidShare(ImportProfile $profile, StagingStats $stats): void
    {
        $percent = $this->setting($profile, 'invalid_rows_max_percent');

        if ($stats->invalid() * 100 > $stats->total() * $percent) {
            throw new ImportRejectedException(__('import.errors.too_many_invalid', [
                'invalid' => $stats->invalid(),
                'total' => $stats->total(),
                'percent' => $percent,
            ]));
        }
    }

    /**
     * @throws ImportRejectedException
     */
    private function checkRecordCount(ImportProfile $profile, StagingStats $stats): void
    {
        $previous = $this->validRecordsOfLastSuccess($profile);

        if ($previous === null || $previous === 0) {
            return;
        }

        $percent = $this->setting($profile, 'min_records_percent_of_previous');

        if ($stats->valid() * 100 < $previous * $percent) {
            throw new ImportRejectedException(__('import.errors.too_few_records', [
                'current' => $stats->valid(),
                'previous' => $previous,
            ]));
        }
    }

    /**
     * Valid records of the last real (not dry) successful run: rows_total counts every
     * staged record, errors counts the invalid ones.
     */
    private function validRecordsOfLastSuccess(ImportProfile $profile): ?int
    {
        $run = ImportRun::query()
            ->where('import_profile_id', $profile->id)
            ->where('status', ImportRunStatus::Success)
            ->where('is_dry_run', false)
            ->latest('id')
            ->first(['rows_total', 'errors']);

        return $run === null ? null : max(0, $run->rows_total - $run->errors);
    }

    private function setting(ImportProfile $profile, string $key): int
    {
        $value = $profile->settings[$key] ?? null;

        return is_numeric($value) ? (int) $value : (int) config("import.thresholds.{$key}");
    }
}
