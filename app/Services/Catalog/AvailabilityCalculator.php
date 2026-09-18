<?php

namespace App\Services\Catalog;

use App\Enums\Availability;
use App\Enums\WarehouseStockStatus;
use Illuminate\Support\Facades\DB;

/**
 * Product availability from the warehouse stocks (TZ §6.5).
 *
 * Hidden warehouses are not counted: a warehouse the manager switched off does not
 * exist for the customer.
 */
final class AvailabilityCalculator
{
    /**
     * The rule itself, without the database.
     *
     * @param  list<WarehouseStockStatus>  $statuses  stocks on visible warehouses
     * @param  bool  $isDiscontinued  the product left the catalog snapshot
     * @param  bool  $isIncoming  the source reports a delivery on its way (API only)
     */
    public static function fromStatuses(array $statuses, bool $isDiscontinued = false, bool $isIncoming = false): Availability
    {
        if ($isDiscontinued) {
            return Availability::Discontinued;
        }

        if (in_array(WarehouseStockStatus::InStock, $statuses, true)) {
            return Availability::InStock;
        }

        if (in_array(WarehouseStockStatus::Low, $statuses, true)) {
            return Availability::Low;
        }

        return $isIncoming ? Availability::Incoming : Availability::OnOrder;
    }

    /**
     * Recalculates every product of the supplier in one query.
     *
     * Products already marked "снят с производства" are left alone: they are put back on
     * sale by SnapshotFinalizer when the supplier sends them again, not by the stocks.
     */
    public function recalculateForSupplier(int $supplierId): void
    {
        DB::update(<<<'SQL'
            UPDATE products AS p
            LEFT JOIN (
                SELECT ps.product_id,
                       MAX(CASE WHEN ps.status = 'in_stock' THEN 1 ELSE 0 END) AS has_in_stock,
                       MAX(CASE WHEN ps.status = 'low' THEN 1 ELSE 0 END) AS has_low
                FROM product_stocks AS ps
                INNER JOIN warehouses AS w ON w.id = ps.warehouse_id AND w.is_visible = 1
                GROUP BY ps.product_id
            ) AS s ON s.product_id = p.id
            SET p.availability = CASE
                    WHEN s.has_in_stock = 1 THEN 'in_stock'
                    WHEN s.has_low = 1 THEN 'low'
                    ELSE 'on_order'
                END,
                p.availability_rank = CASE
                    WHEN s.has_in_stock = 1 OR s.has_low = 1 THEN 1
                    ELSE 3
                END
            WHERE p.supplier_id = ?
              AND p.availability <> 'discontinued'
            SQL, [$supplierId]);
    }
}
