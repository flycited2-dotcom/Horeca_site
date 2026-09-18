<?php

namespace App\Services\Supplier\Import;

/**
 * What the run did to the catalog. Shown on the run page and in the daily digest.
 */
final class ImportCounters
{
    public function __construct(
        public int $created = 0,
        public int $updated = 0,
        public int $unchanged = 0,
        public int $discontinued = 0,
    ) {}

    /**
     * @return array{created: int, updated: int, unchanged: int, discontinued: int}
     */
    public function toArray(): array
    {
        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'unchanged' => $this->unchanged,
            'discontinued' => $this->discontinued,
        ];
    }
}
