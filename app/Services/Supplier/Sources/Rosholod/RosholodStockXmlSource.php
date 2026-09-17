<?php

namespace App\Services\Supplier\Sources\Rosholod;

use App\Enums\ImportEntity;
use App\Models\ImportProfile;
use App\Services\Supplier\Contracts\SupplierFeedInterface;
use App\Services\Supplier\Data\FeedCapabilities;
use App\Services\Supplier\Data\FetchedSource;
use App\Services\Supplier\Data\SupplierProductStock;
use App\Services\Supplier\Data\SupplierRecordError;
use App\Services\Supplier\Data\SupplierStockEntry;
use App\Services\Supplier\Import\SourceFetcher;
use App\Services\Supplier\Import\StockValueMapper;
use App\Services\Supplier\Import\SupplierText;
use App\Services\Supplier\Xml\Cp1251XmlReader;

/**
 * OstatkiYandex.xml: warehouse stocks as text and the unit of measure (TZ §6.1).
 */
final class RosholodStockXmlSource implements SupplierFeedInterface
{
    private readonly StockValueMapper $values;

    public function __construct(
        private readonly SourceFetcher $fetcher,
        private readonly Cp1251XmlReader $xml,
    ) {
        $this->values = new StockValueMapper(config('suppliers.rosholod.stock_values'));
    }

    public function capabilities(): FeedCapabilities
    {
        return new FeedCapabilities(
            entities: [ImportEntity::Stock],
            ownedProductFields: ['unit', 'stocks'],
            isFullSnapshot: true,
        );
    }

    public function fetch(ImportProfile $profile, bool $force = false): ?FetchedSource
    {
        return $this->fetcher->fetch($profile, $force);
    }

    public function read(FetchedSource $source): iterable
    {
        $config = config('suppliers.rosholod.stock');

        foreach ($this->xml->elements($source->path, [$config['product_element']]) as $element) {
            $externalId = trim(Cp1251XmlReader::childTexts($element)[$config['external_id']] ?? '');

            if ($externalId === '') {
                yield new SupplierRecordError(ImportEntity::Stock, '', __('import.records.missing_external_id'));

                continue;
            }

            $entries = [];
            $unit = null;

            foreach (Cp1251XmlReader::descendants($element, $config['stock_element']) as $stock) {
                $texts = Cp1251XmlReader::childTexts($stock);
                $unit ??= SupplierText::clean($texts[$config['unit']] ?? null, 16);
                $warehouse = SupplierText::clean($texts[$config['warehouse']] ?? null, 150);

                // A record without a warehouse name means "no stock anywhere".
                if ($warehouse === null) {
                    continue;
                }

                Cp1251XmlReader::assertReadable($warehouse);

                $balance = SupplierText::clean($texts[$config['balance']] ?? null, 64);
                [$status, $recognized] = $this->values->fromText($balance);

                $entries[] = new SupplierStockEntry(
                    warehouseName: $warehouse,
                    status: $status,
                    rawValue: $balance,
                    warning: $recognized ? null : __('import.records.unknown_stock_value', ['value' => $balance, 'warehouse' => $warehouse]),
                );
            }

            yield new SupplierProductStock($externalId, $entries, $unit);
        }
    }
}
