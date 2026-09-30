<?php

namespace App\Actions\Backup;

/**
 * What a run of the backup left: the file, its size and whether the copy outside the server was made.
 */
final readonly class BackupResult
{
    public function __construct(
        public string $path,
        public int $bytes,
        public bool $offsite,
        public int $removedLocal,
        public int $removedOffsite,
    ) {}
}
