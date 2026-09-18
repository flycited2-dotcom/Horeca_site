<?php

namespace App\Services\Supplier\Import;

use App\Enums\ImportEntity;
use App\Models\ImportRow;
use App\Models\ImportRun;
use App\Services\Supplier\Data\SupplierCategory;
use App\Services\Supplier\Data\SupplierProduct;
use App\Services\Supplier\Data\SupplierProductStock;
use App\Services\Supplier\Data\SupplierRecordError;

/**
 * Writes the records of a source into import_rows (TZ §6.3, step 4).
 *
 * Nothing touches the catalog until the whole source has been staged and checked
 * against the thresholds, so a broken feed cannot damage the shop.
 */
final class StagingWriter
{
    /**
     * @param  iterable<int, SupplierCategory|SupplierProduct|SupplierProductStock|SupplierRecordError>  $records
     */
    public function write(ImportRun $run, iterable $records, ImportLog $log): StagingStats
    {
        $stats = new StagingStats;
        $chunk = (int) config('import.chunk_size');
        $rows = [];
        $seen = [];

        foreach ($records as $record) {
            $row = $this->row($record, $stats, $log);

            $key = $row['entity'].'|'.$row['external_id'];

            if ($row['is_valid'] && isset($seen[$key])) {
                $row['is_valid'] = false;
                $row['error'] = __('import.records.duplicate_external_id');
            }

            if ($row['is_valid']) {
                $seen[$key] = true;
            } else {
                $log->add($this->problem($row));
            }

            $stats->count(ImportEntity::from($row['entity']), $row['is_valid']);

            $rows[] = ['import_run_id' => $run->id] + $row;

            if (count($rows) >= $chunk) {
                ImportRow::query()->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            ImportRow::query()->insert($rows);
        }

        return $stats;
    }

    /**
     * @return array{entity: string, external_id: string, payload: string, hash: string, is_valid: bool, error: string|null}
     */
    private function row(
        SupplierCategory|SupplierProduct|SupplierProductStock|SupplierRecordError $record,
        StagingStats $stats,
        ImportLog $log,
    ): array {
        if ($record instanceof SupplierRecordError) {
            return $this->build($record->entity, $record->externalId, [], $record->reason);
        }

        if ($record instanceof SupplierCategory) {
            return $this->build(ImportEntity::Category, $record->externalId, [
                'external_id' => $record->externalId,
                'parent_external_id' => $record->parentExternalId,
                'name' => $record->name,
            ]);
        }

        if ($record instanceof SupplierProduct) {
            if ($record->brandName !== null) {
                $stats->rememberBrand($record->brandName);
            }

            return $this->build(ImportEntity::Product, $record->externalId, [
                'external_id' => $record->externalId,
                'name' => $record->name,
                'supplier_code' => $record->supplierCode,
                'sku' => $record->sku,
                'model' => $record->model,
                'description' => $record->description,
                'brand_name' => $record->brandName,
                'category_external_id' => $record->categoryExternalId,
                'category_name' => $record->categoryName,
                'rrp_price' => $record->rrpPrice?->toDecimal(),
                'purchase_price' => $record->purchasePrice?->toDecimal(),
            ]);
        }

        $entries = [];

        foreach ($record->entries as $entry) {
            $stats->rememberWarehouse($entry->warehouseName);

            if ($entry->warning !== null) {
                $log->addOnce('stock-value:'.mb_strtolower($entry->rawValue ?? ''), $entry->warning);
            }

            $entries[] = [
                'warehouse_name' => $entry->warehouseName,
                'status' => $entry->status->value,
                'raw_value' => $entry->rawValue,
                'quantity' => $entry->quantity,
            ];
        }

        return $this->build(ImportEntity::Stock, $record->productExternalId, [
            'external_id' => $record->productExternalId,
            'unit' => $record->unit,
            'entries' => $entries,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{entity: string, external_id: string, payload: string, hash: string, is_valid: bool, error: string|null}
     */
    private function build(ImportEntity $entity, string $externalId, array $payload, ?string $error = null): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return [
            'entity' => $entity->value,
            'external_id' => mb_substr($externalId, 0, 191),
            'payload' => $json,
            'hash' => md5($json),
            'is_valid' => $error === null,
            'error' => $error === null ? null : mb_substr($error, 0, 500),
        ];
    }

    /**
     * @param  array{entity: string, external_id: string, payload: string, hash: string, is_valid: bool, error: string|null}  $row
     */
    private function problem(array $row): string
    {
        $entity = ImportEntity::from($row['entity'])->getLabel();
        $id = $row['external_id'] === '' ? '—' : $row['external_id'];

        return "{$entity} {$id}: {$row['error']}";
    }
}
