<?php

namespace App\Services\Supplier\Sync;

use App\Enums\SupplierRefEntity;
use App\Models\Supplier;
use App\Models\SupplierRef;
use App\Services\Supplier\Import\ImportLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Mirrors supplier entities that are identified by name only — brands and warehouses —
 * into supplier_refs and creates our records for the new ones (TZ §6.3, step 6).
 *
 * A record with the same name that is already in the shop is linked instead of being
 * created again: demo data, a manual entry or a lost match must not turn into a duplicate
 * or into a unique key violation in the middle of an import.
 *
 * Names with tails ("Rosso (Китай)") stay as they came: merging them is the manager's
 * call, made in the "Соответствия поставщика" screen.
 */
abstract class NamedRefSync
{
    abstract protected function entity(): SupplierRefEntity;

    /**
     * Our record with this name, if the shop already has one.
     */
    abstract protected function findLocal(Supplier $supplier, string $name): ?Model;

    /**
     * Creates our record for a supplier entity seen for the first time.
     */
    abstract protected function createLocal(Supplier $supplier, string $name): Model;

    /**
     * Ids of our records that still exist, so a deleted one is not used as a link.
     *
     * @param  list<int>  $ids
     * @return array<int, string> id => name
     */
    abstract protected function existingLocals(array $ids): array;

    abstract protected function deletedMessage(string $name): string;

    /**
     * @param  array<string, string>  $items  matching key => supplier name
     * @return array<string, ResolvedRef> matching key => our record
     */
    public function sync(Supplier $supplier, array $items, ImportLog $log): array
    {
        if ($items === []) {
            return [];
        }

        $refs = SupplierRef::query()
            ->where('supplier_id', $supplier->id)
            ->where('entity', $this->entity())
            ->whereIn('external_key', array_keys($items))
            ->get()
            ->keyBy('external_key');

        $locals = $this->existingLocals(
            $refs->pluck('local_id')->filter()->map(fn (mixed $id): int => (int) $id)->values()->all()
        );

        $resolved = [];
        $seenAt = now();

        foreach ($items as $key => $name) {
            $ref = $refs->get($key);

            if ($ref === null) {
                $local = $this->findLocal($supplier, $name) ?? $this->createLocal($supplier, $name);

                SupplierRef::query()->create([
                    'supplier_id' => $supplier->id,
                    'entity' => $this->entity(),
                    'external_key' => $key,
                    'name' => $name,
                    'local_id' => $local->getKey(),
                    'last_seen_at' => $seenAt,
                ]);

                $resolved[$key] = new ResolvedRef((int) $local->getKey(), (string) $local->getAttribute('name'));

                continue;
            }

            $ref->forceFill(['name' => $name, 'last_seen_at' => $seenAt])->save();

            if ($ref->is_ignored || $ref->local_id === null) {
                $resolved[$key] = new ResolvedRef(null, $name);

                continue;
            }

            $localName = $locals[$ref->local_id] ?? null;

            if ($localName === null) {
                $log->addOnce($this->entity()->value.'-deleted:'.$key, $this->deletedMessage($name));
                $resolved[$key] = new ResolvedRef(null, $name);

                continue;
            }

            $resolved[$key] = new ResolvedRef((int) $ref->local_id, $localName);
        }

        return $resolved;
    }
}
