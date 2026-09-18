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
     * @var array<int, array{name: string, parent: int|null}>|null
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
     * The category itself and everything below it.
     *
     * @return list<int>
     */
    public function branch(int $id): array
    {
        $children = [];

        foreach ($this->nodes() as $nodeId => $node) {
            if ($node['parent'] !== null) {
                $children[$node['parent']][] = $nodeId;
            }
        }

        $branch = [];
        $queue = [$id];

        while ($queue !== []) {
            $current = array_shift($queue);

            if (in_array($current, $branch, true)) {
                continue;
            }

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
     * @return array<int, array{name: string, parent: int|null}>
     */
    private function nodes(): array
    {
        return $this->nodes ??= Category::query()
            ->get(['id', 'parent_id', 'name'])
            ->mapWithKeys(fn (Category $category): array => [
                $category->id => ['name' => $category->name, 'parent' => $category->parent_id],
            ])
            ->all();
    }
}
