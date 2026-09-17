<?php

namespace App\Services\Supplier\Data;

use App\Enums\ImportEntity;

/**
 * A record the adapter could not turn into a DTO. It is staged as invalid and counted against thresholds.
 */
final readonly class SupplierRecordError
{
    public function __construct(
        public ImportEntity $entity,
        public string $externalId,
        public string $reason,
    ) {}
}
