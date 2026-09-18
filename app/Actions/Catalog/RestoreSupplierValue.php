<?php

namespace App\Actions\Catalog;

use App\Models\Product;
use InvalidArgumentException;

/**
 * "Вернуть значение поставщика" (TZ §6.6): the field is unlocked and the next import run
 * writes the supplier value again.
 *
 * The source hash is cleared as well: otherwise an unchanged supplier record would be
 * skipped as "без изменений" and the manual value would stay forever.
 */
final class RestoreSupplierValue
{
    public function handle(Product $product, string $field): void
    {
        if (! in_array($field, SaveProductByManager::LOCKABLE_FIELDS, true)) {
            throw new InvalidArgumentException("Field [{$field}] is not written by the import.");
        }

        $locked = array_values(array_diff($product->locked_fields ?? [], [$field]));

        $product->forceFill([
            'locked_fields' => $locked === [] ? null : $locked,
            'source_hash' => null,
        ])->save();
    }
}
