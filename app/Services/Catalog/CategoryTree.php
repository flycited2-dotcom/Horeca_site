<?php

namespace App\Services\Catalog;

use App\Models\Category;

/**
 * The storefront category tree read once per request: full paths for the admin tables
 * and the list of descendants that must not become the parent of a category.
 *
 * Bound as a scoped service, so a queued job or a new request reads a fresh tree.
 */
final class CategoryTree
{
    public const string SEPARATOR = ' › ';

    /**
     * @var array<int, array{name: string, parent: int|null, active: bool}>|null
     */
    private ?array $nodes = null;

    /**
     * id => "Холодильное оборудование › Шкафы холодильные", sorted by path.
     *
     * @return array<int, string>
     */
    public function paths(): array
    {
        $paths = [];

        foreach (array_keys($this->nodes()) as $id) {
            $paths[$id] = $this->path($id);
        }

        asort($paths, SORT_NATURAL | SORT_FLAG_CASE);

        return $paths;
    }

    public function path(int $id): string
    {
        $nodes = $this->nodes();
        $names = [];
        $seen = [];

        for ($current = $id; $current !== null && isset($nodes[$current]) && ! isset($seen[$current]); $current = $nodes[$current]['parent']) {
            $seen[$current] = true;
            array_unshift($names, $nodes[$current]['name']);
        }

        return implode(self::SEPARATOR, $names);
    }

    /**
     * The root section a category belongs to: the header marks it as the current one.
     */
    public function rootOf(int $id): ?int
    {
        $nodes = $this->nodes();
        $seen = [];

        for ($current = $id; isset($nodes[$current]) && ! isset($seen[$current]); $current = $nodes[$current]['parent']) {
            $seen[$current] = true;

            if ($nodes[$current]['parent'] === null) {
                return $current;
            }
        }

        return null;
    }

    /**
     * The category itself and everything below it.
     *
     * @return list<int>
     */
    public function branch(int $id): array
    {
        return $this->walk($id, false);
    }

    /**
     * What a storefront category page shows: the category and its switched-on descendants.
     * A switched-off category hides its whole subtree, including itself.
     *
     * @return list<int>
     */
    public function activeBranch(int $id): array
    {
        return ($this->nodes()[$id]['active'] ?? false) ? $this->walk($id, true) : [];
    }

    /**
     * @return list<int>
     */
    private function walk(int $id, bool $activeOnly): array
    {
        $nodes = $this->nodes();
        $children = [];

        foreach ($nodes as $nodeId => $node) {
            if ($node['parent'] !== null && (! $activeOnly || $node['active'])) {
                $children[$node['parent']][] = $nodeId;
            }
        }

        $branch = [];
        $seen = [];
        $queue = [$id];

        while ($queue !== []) {
            $current = array_shift($queue);

            if (isset($seen[$current])) {
                continue;
            }

            $seen[$current] = true;
            $branch[] = $current;
            array_push($queue, ...($children[$current] ?? []));
        }

        return $branch;
    }

    /**
     * Parents the category may be moved under: anything outside its own branch.
     *
     * @return array<int, string>
     */
    public function parentOptions(?int $categoryId): array
    {
        $paths = $this->paths();

        if ($categoryId === null) {
            return $paths;
        }

        return array_diff_key($paths, array_flip($this->branch($categoryId)));
    }

    /**
     * @return array<int, array{name: string, parent: int|null, active: bool}>
     */
    private function nodes(): array
    {
        return $this->nodes ??= Category::query()
            ->get(['id', 'parent_id', 'name', 'is_active'])
            ->mapWithKeys(fn (Category $category): array => [
                $category->id => ['name' => $category->name, 'parent' => $category->parent_id, 'active' => $category->is_active],
            ])
            ->all();
    }
}
