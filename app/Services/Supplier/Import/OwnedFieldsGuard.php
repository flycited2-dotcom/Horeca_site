<?php

namespace App\Services\Supplier\Import;

use App\Models\ImportProfile;
use App\Services\Supplier\Exceptions\FeedReadException;

/**
 * Every product field belongs to exactly one active profile (TZ §6.2).
 *
 * Two profiles writing the same field would fight each other every hour, so switching on
 * a profile that overlaps with a working one is refused. Turn the old one off first —
 * that is how the XML profiles step aside when the supplier API is connected.
 */
final class OwnedFieldsGuard
{
    public function __construct(private readonly SourceRegistry $sources) {}

    /**
     * The message for the manager, or null when there is no overlap.
     */
    public function conflict(ImportProfile $profile): ?string
    {
        $fields = $this->fieldsOf($profile->source);

        if ($fields === []) {
            return null;
        }

        $others = ImportProfile::query()
            ->where('supplier_id', $profile->supplier_id)
            ->where('is_active', true)
            ->when($profile->exists, fn ($query) => $query->whereKeyNot($profile->getKey()))
            ->get(['id', 'name', 'source']);

        foreach ($others as $other) {
            $shared = array_intersect($fields, $this->fieldsOf($other->source));

            if ($shared !== []) {
                return __('import.errors.owned_fields_conflict', [
                    'fields' => implode(', ', $shared),
                    'profile' => $other->name,
                ]);
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function fieldsOf(string $source): array
    {
        try {
            return $this->sources->make($source)->capabilities()->ownedProductFields;
        } catch (FeedReadException) {
            // An unknown source owns nothing; the form reports it with its own rule.
            return [];
        }
    }
}
