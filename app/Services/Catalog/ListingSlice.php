<?php

namespace App\Services\Catalog;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

/**
 * The pages of a listing on screen (TZ §8.2, layout — screen 2): «Показать ещё» appends the
 * next page to those already shown, the numbered links stay for search engines and for
 * browsers without scripts.
 */
final readonly class ListingSlice
{
    /**
     * @param  Collection<int, Product>  $products  pages $firstPage … $lastPage
     */
    public function __construct(
        public Collection $products,
        public int $total,
        public int $firstPage,
        public int $lastPage,
        public int $perPage = CatalogFilters::PER_PAGE,
    ) {}

    public function pageCount(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    /**
     * Number of the first product on screen, counting from one.
     */
    public function from(): int
    {
        return $this->total === 0 ? 0 : ($this->firstPage - 1) * $this->perPage + 1;
    }

    /**
     * Number of the last product on screen.
     */
    public function to(): int
    {
        return min($this->total, $this->lastPage * $this->perPage);
    }

    /**
     * Share of the listing scrolled through, in whole percent, for the progress line.
     */
    public function progress(): int
    {
        return $this->total === 0 ? 100 : intdiv($this->to() * 100, $this->total);
    }

    public function hasMore(): bool
    {
        return $this->lastPage < $this->pageCount();
    }

    /**
     * How many products «Показать ещё» adds.
     */
    public function nextCount(): int
    {
        return max(0, min($this->perPage, $this->total - $this->to()));
    }

    public function isOnScreen(int $page): bool
    {
        return $page >= $this->firstPage && $page <= $this->lastPage;
    }

    /**
     * Page numbers for the links, null marks a gap: [1, 2, 3, 4, 5, null, 12].
     * The first and the last page are always there, and the pages around the ones on screen.
     *
     * @return list<int|null>
     */
    public function pageLinks(): array
    {
        $count = $this->pageCount();

        if ($count <= 7) {
            return range(1, $count);
        }

        $from = max(2, min($this->firstPage - 1, $count - 4));
        $to = min($count - 1, max($this->lastPage + 1, 5));

        $links = [1];

        if ($from > 2) {
            $links[] = null;
        }

        array_push($links, ...range($from, $to));

        if ($to < $count - 1) {
            $links[] = null;
        }

        $links[] = $count;

        return $links;
    }
}
