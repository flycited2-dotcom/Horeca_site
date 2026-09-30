<?php

namespace App\Actions\Backup;

use RuntimeException;

/**
 * The backup did not come out whole: nothing is written to the backup folder in this case,
 * and the previous copies stay as they were.
 */
final class BackupFailed extends RuntimeException
{
    public static function dumpFailed(string $reason): self
    {
        return new self(__('backup.errors.dump_failed', ['reason' => $reason]));
    }

    public static function truncated(): self
    {
        return new self(__('backup.errors.truncated'));
    }

    public static function tooSmall(int $bytes, int $minimum): self
    {
        return new self(__('backup.errors.too_small', ['bytes' => $bytes, 'minimum' => $minimum]));
    }

    public static function cannotWrite(string $path): self
    {
        return new self(__('backup.errors.cannot_write', ['path' => $path]));
    }

    public static function offsiteFailed(string $reason): self
    {
        return new self(__('backup.errors.offsite_failed', ['reason' => $reason]));
    }
}
