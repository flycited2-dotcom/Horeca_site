<?php

namespace App\Services\Supplier\Sync;

/**
 * A supplier entity matched to ours: the local record and its name as the manager sees it.
 */
final readonly class ResolvedRef
{
    public function __construct(
        public ?int $id,
        public string $name,
    ) {}
}
