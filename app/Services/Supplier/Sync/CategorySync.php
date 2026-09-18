<?php

namespace App\Services\Supplier\Sync;

use App\Enums\ImportEntity;
use App\Enums\SupplierRefEntity;
use App\Models\Category;
use App\Models\ImportRow;
use App\Models\ImportRun;
use App\Models\Supplier;
use App\Models\SupplierRef;
use App\Services\Supplier\Import\ImportLog;
use App\Services\Supplier\Import\SupplierText;
use App\Support\Slugger;

/**
 * Mirrors the supplier category tree into supplier_refs (TZ §6.3, step 6).
 *
 * A category seen for the first time gets a storefront copy switched off, so nothing
 * appears in the shop until the manager checks the name, the place in the tree and the SEO.
 * Names, structure and activity of existing categories are never touched (TZ §6.6).
 */
final class CategorySync
{
    public function sync(ImportRun $run, Supplier $supplier, ImportLog $log): CategoryMap
    {
        $staged = $this->staged($run);

        if ($staged === []) {
            return new CategoryMap([], []);
        }

        $refs = SupplierRef::query()
            ->where('supplier_id', $supplier->id)
            ->where('entity', SupplierRefEntity::Category)
            ->get()
            ->keyBy('external_key');

        $existing = Category::query()
            ->whereIn('id', $refs->pluck('local_id')->filter()->values()->all())
            ->pluck('id')
            ->all();
        $existing = array_flip($existing);

        $slugs = array_flip(Category::query()->pluck('slug')->all());
        $seenAt = now();
        $byKey = [];

        foreach ($this->parentsFirst($staged) as $key) {
            $category = $staged[$key];
            $ref = $refs->get($key);

            if ($ref === null) {
                $parentId = $category['parent'] === null ? null : ($byKey[$category['parent']] ?? null);
                $local = $this->createCategory($category['name'], $parentId, $slugs);

                SupplierRef::query()->create([
                    'supplier_id' => $supplier->id,
                    'entity' => SupplierRefEntity::Category,
                    'external_key' => $key,
                    'name' => $category['name'],
                    'parent_key' => $category['parent'],
                    'local_id' => $local,
                    'last_seen_at' => $seenAt,
                ]);

                $byKey[$key] = $local;

                continue;
            }

            $ref->forceFill([
                'name' => $category['name'],
                'parent_key' => $category['parent'],
                'last_seen_at' => $seenAt,
            ])->save();

            if ($ref->is_ignored || $ref->local_id === null) {
                $byKey[$key] = null;

                continue;
            }

            if (! isset($existing[$ref->local_id])) {
                $log->addOnce("category-deleted:{$key}", __('import.records.category_deleted', ['name' => $category['name']]));
                $byKey[$key] = null;

                continue;
            }

            $byKey[$key] = (int) $ref->local_id;
        }

        return new CategoryMap($byKey, $this->byName($staged, $log));
    }

    /**
     * Staged categories: supplier key => name and parent key.
     *
     * The tree has 25 roots and two levels today, but any depth is read.
     *
     * @return array<string, array{name: string, parent: string|null}>
     */
    private function staged(ImportRun $run): array
    {
        $staged = [];

        ImportRow::query()
            ->where('import_run_id', $run->id)
            ->where('entity', ImportEntity::Category)
            ->where('is_valid', true)
            ->orderBy('id')
            ->each(function (ImportRow $row) use (&$staged): void {
                $payload = $row->payload;

                $staged[$row->external_id] ??= [
                    'name' => (string) $payload['name'],
                    'parent' => $payload['parent_external_id'] ?? null,
                ];
            });

        return $staged;
    }

    /**
     * Orders keys so that a parent is always created before its children.
     *
     * @param  array<string, array{name: string, parent: string|null}>  $staged
     * @return list<string>
     */
    private function parentsFirst(array $staged): array
    {
        $ordered = [];
        $done = [];

        foreach (array_keys($staged) as $key) {
            $chain = [];
            $current = $key;
            $visited = [];

            while ($current !== null && isset($staged[$current]) && ! isset($done[$current]) && ! isset($visited[$current])) {
                $visited[$current] = true;
                $chain[] = $current;
                $current = $staged[$current]['parent'];
            }

            foreach (array_reverse($chain) as $item) {
                $done[$item] = true;
                $ordered[] = $item;
            }
        }

        return $ordered;
    }

    /**
     * Matching name => supplier key. Products point at a category by name, so a repeated
     * name is a conflict: the first category wins and the run log says so (TZ §6.1, fact 5).
     *
     * @param  array<string, array{name: string, parent: string|null}>  $staged
     * @return array<string, string>
     */
    private function byName(array $staged, ImportLog $log): array
    {
        $byName = [];

        foreach ($staged as $key => $category) {
            $name = SupplierText::key($category['name']);

            if (isset($byName[$name])) {
                $log->addOnce("category-ambiguous:{$name}", __('import.records.ambiguous_category', [
                    'name' => $category['name'],
                    'keys' => $byName[$name].', '.$key,
                ]));

                continue;
            }

            $byName[$name] = $key;
        }

        return $byName;
    }

    /**
     * @param  array<string, int>  $slugs
     */
    private function createCategory(string $name, ?int $parentId, array &$slugs): int
    {
        $slug = Slugger::unique(
            $name,
            fn (string $candidate): bool => isset($slugs[$candidate]),
            Slugger::CATEGORY_LIMIT,
        );

        $slugs[$slug] = 1;

        $category = Category::query()->create([
            'parent_id' => $parentId,
            'name' => $name,
            'slug' => $slug,
            'is_active' => false,
            'sort' => 0,
        ]);

        return (int) $category->id;
    }
}
