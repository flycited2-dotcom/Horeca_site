<?php

namespace App\Services\Supplier\Data;

final readonly class FetchedSource
{
    public function __construct(
        public string $path,
        public string $relativePath,
        public string $hash,
        public ?string $etag,
        public ?string $lastModified,
    ) {}
}
