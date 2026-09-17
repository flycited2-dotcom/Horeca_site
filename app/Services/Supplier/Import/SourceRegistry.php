<?php

namespace App\Services\Supplier\Import;

use App\Models\ImportProfile;
use App\Services\Supplier\Contracts\SupplierFeedInterface;
use App\Services\Supplier\Exceptions\FeedReadException;

/**
 * Resolves the adapter of an import profile from config/import.php.
 */
final class SourceRegistry
{
    /**
     * @throws FeedReadException
     */
    public function for(ImportProfile $profile): SupplierFeedInterface
    {
        return $this->make($profile->source);
    }

    /**
     * @throws FeedReadException
     */
    public function make(string $source): SupplierFeedInterface
    {
        $class = $this->sources()[$source] ?? null;

        if ($class === null) {
            throw new FeedReadException(__('import.errors.unknown_source', ['source' => $source]));
        }

        return app($class);
    }

    /**
     * @return array<string, string> source key => label
     */
    public function options(): array
    {
        $options = [];

        foreach (array_keys($this->sources()) as $source) {
            $options[$source] = __("import.sources.{$source}");
        }

        return $options;
    }

    /**
     * @return array<string, class-string<SupplierFeedInterface>>
     */
    private function sources(): array
    {
        return config('import.sources');
    }
}
