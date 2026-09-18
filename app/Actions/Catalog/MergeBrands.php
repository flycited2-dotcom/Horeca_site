<?php

namespace App\Actions\Catalog;

use App\Enums\SupplierRefEntity;
use App\Models\Brand;
use App\Models\Product;
use App\Models\SupplierRef;
use App\Services\Search\SearchTextBuilder;
use App\Support\StorefrontPaths;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * "Объединить бренды" (TZ §12): the supplier sends one trade mark under several names
 * ("Rosso" and "Rosso (Китай)"), the manager keeps one of them.
 *
 * Products move to the kept brand, every supplier name of the merged brand now points
 * at the kept one, so the next import does not bring it back, and the old brand page
 * redirects to the kept one.
 */
final class MergeBrands
{
    public function __construct(
        private readonly SearchTextBuilder $search,
        private readonly RedirectMovedAddress $redirects,
    ) {}

    /**
     * @return int products moved to the kept brand
     */
    public function handle(Brand $merged, Brand $kept): int
    {
        if ($merged->is($kept)) {
            throw new InvalidArgumentException('A brand cannot be merged into itself.');
        }

        return DB::transaction(function () use ($merged, $kept): int {
            $moved = 0;

            Product::withTrashed()
                ->where('brand_id', $merged->id)
                ->select(['id', 'name', 'model', 'sku', 'supplier_code'])
                ->chunkById(500, function (Collection $products) use ($kept, &$moved): void {
                    foreach ($products as $product) {
                        Product::withTrashed()->whereKey($product->id)->toBase()->update([
                            'brand_id' => $kept->id,
                            'search_text' => $this->search->build(
                                $product->name,
                                $product->model,
                                $product->sku,
                                $product->supplier_code,
                                $kept->name,
                            ),
                        ]);

                        $moved++;
                    }
                });

            SupplierRef::query()
                ->where('entity', SupplierRefEntity::Brand)
                ->where('local_id', $merged->id)
                ->update(['local_id' => $kept->id]);

            $this->redirects->handle(StorefrontPaths::brand($merged->slug), StorefrontPaths::brand($kept->slug));

            $merged->delete();

            return $moved;
        });
    }
}
